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
use Cosray\Assets\SizeSpec;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Exception\IngestError;
use Cosray\Exception\RuntimeException;
use Cosray\Middleware\Permission;
use Cosray\Users;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;

class Media
{
	protected ?Assets $assets = null;

	public function __construct(
		protected readonly Factory $factory,
		protected readonly Request $request,
		protected readonly Config $config,
		protected readonly Database $db,
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

	/** Build the client payload for a catalog row. */
	protected function uploadResult(array $row): array
	{
		$asset = Asset::fromRow($row, $this->config);

		return [
			'ok' => true,
			'error' => '',
			'uid' => $asset->uid,
			'filename' => $asset->filename,
			'kind' => $asset->kind,
			'mime' => $asset->mime,
			'bytes' => $asset->bytes,
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
