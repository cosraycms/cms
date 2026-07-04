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
use Cosray\Assets\Assets;
use Cosray\Assets\ResizeMode;
use Cosray\Assets\Size;
use Cosray\Auth;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Middleware\Permission;
use Cosray\Storage\Storage;
use Cosray\Uid;
use Cosray\Users;
use enshrined\svgSanitize\Sanitizer;
use Gumlet\ImageResize;
use Psr\Http\Message\UploadedFileInterface as PsrUploadedFile;
use Throwable;

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
		$ext = strtolower(pathinfo($result['file'], PATHINFO_EXTENSION));
		$key = Storage::key($uid, $ext);
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
		$prefix = $this->config->path->prefix;
		$uid = (string) $row['uid'];
		$kind = (string) $row['kind'];
		$filename = (string) $row['filename'];
		$url = "{$prefix}/media/{$kind}/{$uid}/" . rawurlencode($filename);

		return [
			'uid' => $uid,
			'filename' => $filename,
			'url' => $url,
			'thumbUrl' => $kind === 'image' ? "{$url}?resize=width&w=400" : $url,
			'kind' => $kind,
			'mime' => $row['mime'] ?? null,
			'width' => isset($row['width']) ? (int) $row['width'] : null,
			'height' => isset($row['height']) ? (int) $row['height'] : null,
		];
	}

	/** Build the client payload for a catalog row. */
	protected function uploadResult(array $row): array
	{
		$prefix = $this->config->path->prefix;
		$uid = (string) $row['uid'];
		$filename = (string) $row['filename'];
		$kind = (string) $row['kind'];

		return [
			'ok' => true,
			'error' => '',
			'uid' => $uid,
			'filename' => $filename,
			'mime' => $row['mime'] ?? null,
			'width' => isset($row['width']) ? (int) $row['width'] : null,
			'height' => isset($row['height']) ? (int) $row['height'] : null,
			'url' => "{$prefix}/media/{$kind}/{$uid}/" . rawurlencode($filename),
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

	public function image(string $slug): Response
	{
		$image = $this->getAssets()->image($this->assetKey($slug));
		$qs = $this->request->params();

		if ($qs['resize'] ?? null) {
			[$size, $mode] = match ($qs['resize']) {
				ResizeMode::Width->value => [new Size((int) $qs['w']), ResizeMode::Width],
				ResizeMode::Height->value => [new Size((int) $qs['h']), ResizeMode::Height],
				ResizeMode::LongSide->value => [new Size((int) $qs['size']), ResizeMode::LongSide],
				ResizeMode::ShortSide->value => [new Size((int) $qs['size']), ResizeMode::ShortSide],
				ResizeMode::Fit->value => [new Size((int) $qs['w'], (int) $qs['h']), ResizeMode::Fit],
				ResizeMode::Resize->value => [new Size((int) $qs['w'], (int) $qs['h']), ResizeMode::Resize],
				ResizeMode::FreeCrop->value => [new Size((int) $qs['w'], (int) $qs['h'], [
						'x' => $qs['x'] ? (int) $qs['x'] : false,
						'y' => $qs['y'] ? (int) $qs['y'] : false,
					]), ResizeMode::FreeCrop],
				ResizeMode::Crop->value => [new Size((int) $qs['w'], (int) $qs['h'], match ($qs['pos']) {
						'top' => ImageResize::CROPTOP,
						'centre' => ImageResize::CROPCENTRE,
						'center' => ImageResize::CROPCENTER,
						'bottom' => ImageResize::CROPBOTTOM,
						'left' => ImageResize::CROPLEFT,
						'right' => ImageResize::CROPRIGHT,
						'topcenter' => ImageResize::CROPTOPCENTER,
						default => throw new RuntimeException('Crop position not supported: ' . $qs['pos']),
					}), ResizeMode::Crop],
				default => throw new RuntimeException('Resize mode not supported: ' . $qs['resize']),
			};

			$quality = $qs['quality'] ?? null ? (int) $qs['quality'] : null;
			$image->resize($size, $mode, $qs['enlarge'] ?? false, $quality);
		}

		$fileServer = $this->config->media->fileServer;

		if ($fileServer) {
			return $this->sendFile($fileServer, $image->path());
		}

		return Response::create($this->factory)->file($image->path());
	}

	public function file(string $slug): Response
	{
		$file = $this->getAssets()->file($this->assetKey($slug));

		try {
			$path = $file->path();
		} catch (RuntimeException $e) {
			throw new HttpNotFound($this->request, previous: $e);
		}

		$fileServer = $this->config->media->fileServer;

		if ($fileServer) {
			return $this->sendFile($fileServer, $path);
		}

		return Response::create($this->factory)->file($path);
	}

	/**
	 * Resolve a media slug `{assetUid}/{name}` to the asset's pool key.
	 * Resolution keys on the uid alone; the name segment is cosmetic.
	 */
	protected function assetKey(string $slug): string
	{
		$uid = explode('/', $slug, 2)[0];
		$row = $this->db->assets->byUid(['uid' => $uid])->first();

		if (!$row) {
			throw new HttpNotFound($this->request);
		}

		if ($row['disk'] !== 'local') {
			throw new RuntimeException('Asset disk not supported: ' . $row['disk']);
		}

		return (string) $row['key'];
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

		$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = (string) finfo_buffer($fileInfo, $contents);
		finfo_close($fileInfo);
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
		return $this->assets ??= new Assets($this->request, $this->config);
	}
}
