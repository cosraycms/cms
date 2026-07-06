<?php

declare(strict_types=1);

namespace Cosray\Controller;

use Celemas\Core\Exception\HttpNotFound;
use Celemas\Core\Exception\OutOfBoundsException;
use Celemas\Core\Exception\RuntimeException as CoreRuntimeException;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;
use Celemas\Quma\Database;
use Cosray\Assets\Asset;
use Cosray\Assets\Assets;
use Cosray\Assets\Meta;
use Cosray\Assets\SizeSpec;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Locales;
use Cosray\Middleware\Permission;
use Cosray\References\Usage;
use Cosray\Storage\Storage;
use Cosray\Uid;
use Cosray\Users;
use enshrined\svgSanitize\Sanitizer;
use finfo;
use PDOException;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

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
		$contents =
			$file !== null && $file->getError() === UPLOAD_ERR_OK
				? (string) $file->getStream()
				: '';
		$result = $this->validateUploadedFile($mediatype, $file, $contents);

		if (!$result['ok']) {
			return $response->json($result, 400);
		}

		// SVGs are served inline, so a stored `<script>`/`onload` would run in
		// the site origin. Clean the markup before it lands in the pool; hash
		// and byte count are taken from the sanitized bytes.
		if (strtolower(pathinfo($result['file'], PATHINFO_EXTENSION)) === 'svg') {
			$clean = self::sanitizeSvgMarkup($contents);

			if ($clean === null) {
				return $response->json(
					['ok' => false, 'error' => _('Die SVG-Datei konnte nicht sicher verarbeitet werden.')],
					400,
				);
			}

			$contents = $clean;
		}

		$storage = new Storage($this->config);
		$hash = hash('sha256', $contents);
		$existing = $this->db
			->assets
			->byHash([
				'hash' => $hash,
				'disk' => $storage->disk,
			])
			->first();

		if ($existing) {
			return $response->json($this->uploadResult($existing));
		}

		[$width, $height] = $this->imageDimensions($mediatype, $contents);
		$uidConfig = $this->config->uid;
		$uid = new Uid($uidConfig->alphabet, $uidConfig->length)->generate();
		$key = Storage::key($uid, $result['file']);
		$storage->write($key, $contents);

		try {
			$this->db->assets->create([
				'uid' => $uid,
				'disk' => $storage->disk,
				'key' => $key,
				'filename' => $result['file'],
				'mime' => $result['mime'],
				'bytes' => strlen($contents),
				'width' => $width,
				'height' => $height,
				'kind' => $mediatype,
				'hash' => $hash,
				'meta' => '{}',
				'creator' => $this->userId(),
			])->one();
		} catch (Throwable $e) {
			$storage->delete($key);

			throw $e;
		}

		return $response->json($this->uploadResult([
			'uid' => $uid,
			'disk' => $storage->disk,
			'key' => $key,
			'filename' => $result['file'],
			'mime' => $result['mime'],
			'width' => $width,
			'height' => $height,
			'kind' => $mediatype,
		]));
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

		if (in_array($kind, ['image', 'video'], true)) {
			$args['kind'] = $kind;
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
			return $response->json(['ok' => false, 'error' => _('Unbekannte Datei')], 404);
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
			return $response->json(['ok' => false, 'error' => _('Unbekannte Datei')], 404);
		}

		$stored = json_decode((string) ($row['meta'] ?? '{}'), true);
		$input = $this->request->json();
		$meta = Meta::apply(
			is_array($stored) ? $stored : [],
			is_array($input) ? $input['meta'] ?? $input : [],
			$this->localeIds(),
			$row['kind'] === 'image',
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
			return $response->json(['ok' => false, 'error' => _('Unbekannte Datei')], 404);
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

	/** @return array{0: ?int, 1: ?int} */
	protected function imageDimensions(string $mediatype, string $contents): array
	{
		if ($mediatype !== 'image') {
			return [null, null];
		}

		// getimagesizefromstring warns on undecodable input (e.g. SVG bytes);
		// unreadable dimensions are an expected outcome here, not an error.
		set_error_handler(static fn(): bool => true);

		try {
			$info = getimagesizefromstring($contents);
		} finally {
			restore_error_handler();
		}

		return $info === false ? [null, null] : [$info[0], $info[1]];
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
	 * Strip scripts, event handlers and remote references from SVG markup.
	 * Returns null when the sanitizer rejects the markup as malformed.
	 */
	public static function sanitizeSvgMarkup(string $svg): ?string
	{
		$clean = new Sanitizer()->sanitize($svg);

		return $clean === false ? null : $clean;
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

	/**
	 * Reduce a client-supplied upload name to a safe on-disk basename:
	 * strip every directory component (and any `../`), drop control
	 * characters, and trim leading/trailing dots and spaces.
	 */
	public static function safeFilename(string $name): string
	{
		$name = basename($name);
		$name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

		return trim($name, ' .');
	}

	protected function validateUploadedFile(
		string $mediatype,
		?PsrUploadedFile $file,
		string $contents,
	): array {
		if (!$file) {
			return [
				'ok' => false,
				'error' => _('Upload fehlgeschlagen. Datei konnte am Server nicht verabeitet werden.'),
				'file' => _(' Dateiname unbekannt'),
			];
		}
		$upload = $this->config->upload;
		$mimeTypes = match ($mediatype) {
			'file' => $upload->file,
			'image' => $upload->image,
			'video' => $upload->video,
			default => throw new RuntimeException('Media type not supported: ' . $mediatype),
		};
		$maxSize = $upload->maxSize;

		$fileSize = $file->getSize() ?? strlen($contents);
		$fileName = self::safeFilename((string) ($file->getClientFilename() ?? ''));

		if ($fileName === '') {
			return [
				'ok' => false,
				'error' => _('Upload fehlgeschlagen. Datei konnte am Server nicht verabeitet werden.'),
				'file' => _(' Dateiname unbekannt'),
			];
		}

		$pathInfo = pathinfo($fileName);
		$ext = $pathInfo['extension'] ?? null;
		$result = [
			'ok' => true,
			'file' => $fileName,
			'error' => '',
			'code' => 0,
		];

		if ($file->getError() === UPLOAD_ERR_INI_SIZE || $fileSize > $maxSize) {
			$size = number_format((float) (($fileSize / 1024) / 1024), 2, '.', '');
			$allowed = number_format((float) (($maxSize / 1024) / 1024), 2, '.', '');

			return array_merge($result, [
				'ok' => false,
				'error' => "Die Datei ist zu groß: {$size} MB. Erlaubt sind {$allowed} MB",
			]);
		}

		if ($file->getError() !== UPLOAD_ERR_OK) {
			return array_merge($result, [
				'ok' => false,
				'error' => _('Der Dateiupload ist aufgrund eines Serverfehlers fehlgeschlagen.'),
			]);
		}

		$mimeType = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);
		$allowedExtensions = $mimeTypes[$mimeType] ?? null;
		$result['mime'] = $mimeType;

		if (!$allowedExtensions) {
			return array_merge($result, [
				'ok' => false,
				'error' => _("Der Dateityp ist nicht erlaubt: {$mimeType}."),
			]);
		}

		if (!$ext || !in_array(strtolower($ext), $allowedExtensions, true)) {
			return array_merge($result, [
				'ok' => false,
				'error' => _(
					"Falsche Dateiendung: {$ext}. Für diesen Dateityp sind folgende Endungen erlaubt: "
					. implode(', ', $allowedExtensions)
					. '.',
				),
			]);
		}

		return $result;
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
