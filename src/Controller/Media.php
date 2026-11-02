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
use Cosray\Assets\Ingest;
use Cosray\Assets\Renditions;
use Cosray\Assets\Sizes;
use Cosray\Assets\SizeSpec;
use Cosray\Assets\Transform;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Exception\IngestError;
use Cosray\Exception\RuntimeException;
use Cosray\Middleware\Permission;
use Cosray\Users;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\LogLevel;

class Media
{
	/** PHP's upload error codes besides the size limit, by name. */
	private const array UPLOAD_ERRORS = [
		UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
		UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
		UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
		UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
		UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
		UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
	];

	public function __construct(
		protected readonly Factory $factory,
		protected readonly Request $request,
		protected readonly Config $config,
		protected readonly Database $db,
		protected readonly Logger $logger,
	) {}

	#[Permission('edit-nodes')]
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
			// A missing or unwritable temp directory or a blocking extension
			// fails every upload until someone fixes the server; the others
			// are interrupted or incomplete requests.
			$serverSide = in_array($error, [UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION], true);
			$this->logger->log($serverSide ? LogLevel::ERROR : LogLevel::INFO, 'Upload of {file} failed: {code}', [
				'file' => $filename,
				'code' => self::UPLOAD_ERRORS[$error] ?? (string) $error,
			]);

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
	 * Build the client payload for a catalog row.
	 *
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
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
			'meta' => $asset->meta,
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
	 * Only sizes configured in `media.sizes` and variants the server signed
	 * are generated — anything else is a 404, which bounds what this route
	 * can write to disk. An original that cannot be resized redirects to
	 * itself.
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

		[$spec, $transform] = $this->rendition($asset, $file);

		$renditions = new Renditions($this->config);

		try {
			$renditions->original($asset);
		} catch (RuntimeException $e) {
			throw new HttpNotFound($this->request, previous: $e);
		}

		try {
			$path = $renditions->render($asset, $file, $spec, $transform);
		} catch (RuntimeException $e) {
			$this->logger->warning('Rendition {file} failed, redirecting to the original: {error}', [
				'file' => $slug,
				'error' => $e->getMessage(),
			]);

			// Temporary, so the rendition takes over once the cause is gone.
			return Response::create($this->factory)->redirect($asset->path());
		}

		$fileServer = $this->config->media->fileServer;

		if ($fileServer) {
			return $this->sendFile($fileServer, $path);
		}

		return Response::create($this->factory)->file($path);
	}

	/**
	 * Match a requested rendition basename against the configured sizes:
	 * a size and its fingerprint, and a signed variant for a use's crop or
	 * focal point. Only the name the asset would give it counts, which leaves
	 * no other way to the same file and no way to one of an older definition.
	 *
	 * @return array{SizeSpec, ?Transform}
	 */
	protected function rendition(Asset $asset, string $file): array
	{
		$parts = $asset->renditionParts($file);

		if ($parts === null) {
			throw new HttpNotFound($this->request);
		}

		$size = $parts[0];
		$sizes = $this->config->media->sizes;
		$secret = $this->config->app->secret;
		$spec = null;
		$variant = '';
		$transform = null;

		if (count($parts) === 2 && $sizes->has($size)) {
			$spec = $sizes->get($size);
		}

		if (count($parts) === 4 && $secret !== null) {
			[, , $token, $signature] = $parts;
			$transform = Transform::verified($asset->uid, $size, $token, $signature, $secret);
			$variant = "~{$token}~{$signature}";

			if ($transform !== null) {
				$spec = match (true) {
					$size === Sizes::ORIGINAL => Sizes::original(),
					$sizes->has($size) => $sizes->get($size),
					default => null,
				};
			}
		}

		if ($spec === null || $asset->renditionFile($spec, $variant) !== $file) {
			throw new HttpNotFound($this->request);
		}

		return [$spec, $transform];
	}

	protected function sendFile(string $fileServer, string $file): Response
	{
		$response = Response::create($this->factory);
		$response->header('Content-Type', mime_content_type($file) ?: 'application/octet-stream');

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
}
