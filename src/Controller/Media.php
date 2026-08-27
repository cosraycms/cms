<?php

declare(strict_types=1);

namespace Cosray\Controller;

use Celema\Core\Exception\HttpNotFound;
use Celema\Core\Exception\OutOfBoundsException;
use Celema\Core\Exception\RuntimeException as CoreRuntimeException;
use Celema\Core\Factory\Factory;
use Celema\Core\Request;
use Celema\Core\Response;
use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Assets\Asset;
use Cosray\Assets\Assets;
use Cosray\Assets\Ingest;
use Cosray\Assets\Meta;
use Cosray\Assets\SizeSpec;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Exception\IngestError;
use Cosray\Exception\RuntimeException;
use Cosray\Locales;
use Cosray\Middleware\Permission;
use Cosray\References\Usage;
use Cosray\Storage\Storage;
use Cosray\Users;
use PDOException;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Media
{
	protected ?Assets $assets = null;

	public function __construct(
		protected readonly Factory $factory,
		protected readonly Request $request,
		protected readonly Config $config,
		protected readonly Database $db,
		protected readonly Locales $locales,
	) {}

	#[Permission('panel')]
	public function upload(string $mediatype): Response
	{
		$response = Response::create($this->factory);
		$file = $this->uploadedFile();
		$filename = $file !== null
			? Ingest::safeFilename((string) ($file->getClientFilename() ?? ''))
			: '';

		if ($file === null || $filename === '') {
			return $response->json([
				'ok' => false,
				'error' => __('media:upload-failed'),
				'file' => __('media:unknown-filename'),
			], 400);
		}

		$error = $file->getError();
		$contents = $error === UPLOAD_ERR_OK ? (string) $file->getStream() : '';
		$fileSize = $file->getSize() ?? strlen($contents);
		$maxSize = $this->config->upload->maxSize;

		// PHP truncates oversized uploads before the stream reaches us, so
		// this limit check must run on the transport size, not the bytes.
		if ($error === UPLOAD_ERR_INI_SIZE || $fileSize > $maxSize) {
			return $this->ingestFailure($response, IngestError::tooLarge($fileSize, $maxSize), $filename);
		}

		if ($error !== UPLOAD_ERR_OK) {
			return $response->json([
				'ok' => false,
				'file' => $filename,
				'error' => __('media:upload-server-error'),
				'code' => 0,
			], 400);
		}

		try {
			$result = new Ingest($this->config, $this->db)->ingest(
				$contents,
				$filename,
				$mediatype,
				new Actor($this->userId()),
			);
		} catch (IngestError $e) {
			return $this->ingestFailure($response, $e, $filename);
		}

		return $response->json($this->uploadResult($result->row));
	}

	protected function ingestFailure(Response $response, IngestError $e, string $filename): Response
	{
		$payload = [
			'ok' => false,
			'file' => $filename,
			'error' => $e->userMessage,
			'code' => 0,
		];

		if ($e->mime !== null) {
			$payload['mime'] = $e->mime;
		}

		return $response->json($payload, 400);
	}

	/**
	 * Paged asset catalog listing for the panel (library picker, link
	 * modal). `kind` filters to image or video; a File field accepts
	 * every kind, so `file` (or no kind) lists everything.
	 */
	#[Permission('panel')]
	public function library(): Response
	{
		$params = $this->request->params();
		$kind = $params['kind'] ?? null;
		$q = trim((string) ($params['q'] ?? ''));
		$page = max(1, (int) ($params['page'] ?? 1));
		$limit = 60;
		$args = ['limit' => $limit + 1, 'offset' => ($page - 1) * $limit];

		// The prefixes `Asset::classify()` reduces to a kind, read backwards.
		if (in_array($kind, ['image', 'video'], true)) {
			$args['mime'] = $kind . '/%';
		}

		if ($q !== '') {
			$args['q'] = '%' . addcslashes($q, '%_\\') . '%';
		}

		if (isset($params['uids']) && $params['uids'] !== '') {
			$args['uids'] = explode(',', (string) $params['uids']);
		}

		$rows = $this->db->assets->list($args)->all();
		$more = count($rows) > $limit;

		return Response::create($this->factory)->json([
			'ok' => true,
			'assets' => array_map($this->libraryItem(...), array_slice($rows, 0, $limit)),
			'page' => $page,
			'more' => $more,
			// 0 when paging past the end: the window count needs a row to ride on.
			'total' => $rows === [] ? 0 : (int) $rows[0]['total'],
		]);
	}

	protected function libraryItem(array $row): array
	{
		$asset = Asset::fromRow($row, $this->config);

		return [
			'uid' => $asset->uid,
			'filename' => $asset->filename,
			'url' => $asset->path(),
			'thumbUrl' => $asset->resizable() ? $asset->sizePath('thumb') : $asset->path(),
			'previewUrl' => $asset->resizable() ? $asset->sizePath('preview') : $asset->path(),
			'kind' => $asset->kind,
			'mime' => $asset->mime,
			'width' => $asset->width,
			'height' => $asset->height,
		];
	}

	/**
	 * Single-asset detail for the media panel: the catalog row plus its
	 * editable meta and the display-ready usage list (who points at it).
	 */
	#[Permission('panel')]
	public function detail(string $uid): Response
	{
		$response = Response::create($this->factory);
		$row = $this->db->assets->byUid(['uid' => $uid])->first();

		if (!$row) {
			return $response->json(['ok' => false, 'error' => __('media:unknown-file')], 404);
		}

		return $response->json([
			'ok' => true,
			'asset' => $this->detailItem(Asset::fromRow($row, $this->config), $row),
			'usage' => new Usage($this->db)->forAsset($uid),
		]);
	}

	/**
	 * Persist the editable meta slice (localized alt/title/caption,
	 * scalar credit, image focal point). The submitted patch replaces
	 * the managed keys and leaves the rest of the bag untouched.
	 */
	#[Permission('panel')]
	public function updateMeta(string $uid): Response
	{
		$response = Response::create($this->factory);
		$row = $this->db->assets->byUid(['uid' => $uid])->first();

		if (!$row) {
			return $response->json(['ok' => false, 'error' => __('media:unknown-file')], 404);
		}

		$stored = json_decode((string) ($row['meta'] ?? '{}'), true);
		$input = $this->request->json();
		$meta = Meta::apply(
			is_array($stored) ? $stored : [],
			is_array($input) ? $input['meta'] ?? $input : [],
			$this->localeIds(),
			Asset::fromRow($row, $this->config)->kind === 'image',
		);

		$this->db->assets->updateMeta(['uid' => $uid, 'meta' => json_encode($meta)])->run();

		return $response->json(['ok' => true, 'meta' => $meta]);
	}

	protected function detailItem(Asset $asset, array $row): array
	{
		return [
			'uid' => $asset->uid,
			'filename' => $asset->filename,
			'kind' => $asset->kind,
			'mime' => $asset->mime,
			'bytes' => $asset->bytes,
			'width' => $asset->width,
			'height' => $asset->height,
			'url' => $asset->path(),
			'previewUrl' => $asset->resizable() ? $asset->sizePath('preview') : $asset->path(),
			'created' => isset($row['created']) ? (string) $row['created'] : null,
			'meta' => $asset->meta,
		];
	}

	/** @return list<string> */
	protected function localeIds(): array
	{
		$ids = [];

		foreach ($this->locales as $locale) {
			$ids[] = $locale->id;
		}

		return $ids;
	}

	/**
	 * Hard delete, unreferenced-only: the usage check answers 409 with
	 * a display-ready owner list; the RESTRICT FK on `asset_references`
	 * is the backstop against references appearing mid-request. The
	 * catalog row goes first — a leftover file is a harmless orphan, a
	 * dangling row is not.
	 */
	#[Permission('panel')]
	public function delete(string $uid): Response
	{
		$response = Response::create($this->factory);
		$row = $this->db->assets->byUid(['uid' => $uid])->first();

		if (!$row) {
			return $response->json(['ok' => false, 'error' => __('media:unknown-file')], 404);
		}

		$usage = new Usage($this->db);
		$owners = $usage->forAsset($uid);

		if ($owners !== []) {
			return $response->json(['ok' => false, 'usage' => $owners], 409);
		}

		try {
			$this->db->assets->delete(['uid' => $uid])->run();
		} catch (PDOException $e) {
			// RESTRICT violations report SQLSTATE 23001; plain FK
			// violations 23503.
			if (in_array((string) $e->getCode(), ['23001', '23503'], true)) {
				return $response->json(['ok' => false, 'usage' => $usage->forAsset($uid)], 409);
			}

			throw $e;
		}

		if ($row['disk'] === 'local') {
			new Storage($this->config)->deleteDirectory(dirname((string) $row['key']));
			$this->purgeRenditions((string) $row['key']);
		}

		return $response->json(['ok' => true]);
	}

	/** Removes the rendition cache directory `{cache}/{shard}/{uid}/`. */
	protected function purgeRenditions(string $key): void
	{
		$root = rtrim($this->config->path->public, '\\/') . '/' . trim($this->config->path->cache, '/');
		$dir = $root . '/' . dirname($key);

		if (!is_dir($dir) || !str_starts_with((string) realpath($dir), (string) realpath($root))) {
			return;
		}

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($files as $file) {
			$file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
		}

		rmdir($dir);
	}

	/** Build the client payload for a catalog row. */
	protected function uploadResult(array $row): array
	{
		$asset = Asset::fromRow($row, $this->config);

		return [
			'ok' => true,
			'error' => '',
			'uid' => $asset->uid,
			'filename' => $asset->filename,
			'mime' => $asset->mime,
			'width' => $asset->width,
			'height' => $asset->height,
			'url' => $asset->path(),
			'thumbUrl' => $asset->resizable() ? $asset->sizePath('thumb') : $asset->path(),
			'previewUrl' => $asset->resizable() ? $asset->sizePath('preview') : $asset->path(),
		];
	}

	protected function uploadedFile(): ?PsrUploadedFile
	{
		try {
			return $this->request->file('file');
		} catch (CoreRuntimeException|OutOfBoundsException) {
			return null;
		}
	}

	protected function userId(): int
	{
		$auth = new Auth(
			$this->request->unwrap(),
			new Users($this->db),
			$this->config,
			$this->request->get('session', null),
		);
		$user = $auth->user();

		if (!$user) {
			throw new RuntimeException('Upload requires an authenticated user');
		}

		return $user->id;
	}

	/**
	 * Fallback for rendition URLs whose file does not exist yet: the web
	 * server serves `{path.cache}/{shard}/{uid}/{stem}-{size}.{ext}`
	 * natively once generated, so PHP only ever sees the first request.
	 * Only sizes configured in `media.sizes` are generated — anything
	 * else is a 404, which bounds what this route can write to disk.
	 */
	public function cache(string $slug): Response
	{
		$segments = explode('/', $slug);

		if (count($segments) !== 3) {
			throw new HttpNotFound($this->request);
		}

		[$shard, $uid, $file] = $segments;
		$row = $this->db->assets->byUid(['uid' => $uid])->first();

		if (!$row || $row['disk'] !== 'local') {
			throw new HttpNotFound($this->request);
		}

		$asset = Asset::fromRow($row, $this->config);

		if (dirname($asset->key) !== "{$shard}/{$uid}" || !$asset->resizable()) {
			throw new HttpNotFound($this->request);
		}

		$spec = $this->sizeSpec($asset->key, $file);

		try {
			$image = $this
				->getAssets()
				->image($asset->key)
				->resize(
					$spec->size(),
					$spec->mode,
					$spec->enlarge,
					$spec->quality,
					$spec->name,
				);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($this->request, previous: $e);
		}

		$fileServer = $this->config->media->fileServer;

		if ($fileServer) {
			return $this->sendFile($fileServer, $image->path());
		}

		return Response::create($this->factory)->file($image->path());
	}

	/**
	 * Match a requested rendition basename against the asset's key and
	 * the configured sizes: `{stem}-{size}` with the key's extension.
	 */
	protected function sizeSpec(string $key, string $file): SizeSpec
	{
		$base = basename($key);
		$dot = strrpos($base, '.');
		$stem = $dot === false || $dot === 0 ? $base : substr($base, 0, $dot);
		$ext = $dot === false || $dot === 0 ? '' : substr($base, $dot);
		$sizes = $this->config->media->sizes;

		if (str_starts_with($file, "{$stem}-") && ($ext === '' || str_ends_with($file, $ext))) {
			$name = substr($file, strlen($stem) + 1, strlen($file) - strlen($stem) - 1 - strlen($ext));

			if ($name !== '' && $sizes->has($name)) {
				return $sizes->get($name);
			}
		}

		throw new HttpNotFound($this->request);
	}

	protected function sendFile(string $fileServer, string $file): Response
	{
		$response = Response::create($this->factory);
		$response->header('Content-Type', mime_content_type($file));

		switch ($fileServer) {
			case 'apache':
				// apt install libapache2-mod-xsendfile
				// a2enmod xsendfile
				// Apache config:
				//    XSendFile On
				//    XSendFilePath "/path/to/files"
				$response->header('X-Sendfile', $file);
				break;
			case 'nginx':
				// Nginx config
				//   location /path/to/files/ {
				//       internal;
				//           alias   /some/path/; # note the trailing slash
				//       }
				//   }

				$response->header('X-Accel-Redirect', $file);
				break;
			default:
				throw new RuntimeException(
					'File server not supported: `' . $fileServer . '`. Supported values `nginx`, `apache`.',
				);
		}

		return $response;
	}

	protected function getAssets(): Assets
	{
		return $this->assets ??= new Assets($this->config);
	}
}
