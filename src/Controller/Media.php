<?php

declare(strict_types=1);

namespace Celemas\Cms\Controller;

use Celemas\Cms\Assets\Assets;
use Celemas\Cms\Assets\ResizeMode;
use Celemas\Cms\Assets\Size;
use Celemas\Cms\Config;
use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Middleware\Permission;
use Celemas\Core\Factory\Factory;
use Celemas\Core\Request;
use Celemas\Core\Response;
use Gumlet\ImageResize;

class Media
{
	public function __construct(
		protected readonly Factory $factory,
		protected readonly Request $request,
		protected readonly Config $config,
	) {}

	/**
	 * TODO: sanitize filename.
	 */
	#[Permission('panel')]
	public function upload(string $mediatype, string $doctype, string $uid): Response
	{
		$response = Response::create($this->factory);
		$file = $_FILES['file'] ?? null;

		$result = $this->validateUploadedFile($mediatype, $file);

		if (!$result['ok']) {
			return $response->json($result, 400);
		}

		$public = $this->config->path->public;
		$assets = $this->config->path->assets;
		$dir = "{$public}{$assets}/{$doctype}/{$uid}";

		if (!is_dir($dir)) {
			mkdir($dir, 0o755, true);
		}

		move_uploaded_file($file['tmp_name'], "{$dir}/{$result['file']}");

		return $response->json($result);
	}

	public function image(string $slug): Response
	{
		$image = $this->getAssets()->image($slug);
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
		$file = $this->getAssets()->file($slug);
		$fileServer = $this->config->media->fileServer;

		if ($fileServer) {
			return $this->sendFile($fileServer, $file->path());
		}

		return Response::create($this->factory)->file($file->path());
	}

	protected function validateUploadedFile(string $mediatype, ?array $file): array
	{
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

		$tmpFile = $file['tmp_name'];
		$fileSize = filesize($tmpFile);
		$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = finfo_file($fileInfo, $tmpFile);
		finfo_close($fileInfo);
		$fileName = $file['full_path'];
		$pathInfo = pathinfo($fileName);
		$ext = $pathInfo['extension'] ?? null;
		$allowedExtensions = $mimeTypes[$mimeType] ?? null;
		$result = [
			'ok' => true,
			'file' => $fileName,
			'error' => '',
			'code' => 0,
		];

		if (($file['error'] ?? null === UPLOAD_ERR_INI_SIZE) || $fileSize > $maxSize) {
			$size = number_format((float) (($fileSize / 1024) / 1024), 2, '.', '');
			$allowed = number_format((float) (($maxSize / 1024) / 1024), 2, '.', '');

			return array_merge($result, [
				'ok' => false,
				'error' => "Die Datei ist zu groß: {$size} MB. Erlaubt sind {$allowed} MB",
			]);
		}

		if ($file['error'] ?? null !== UPLOAD_ERR_OK) {
			return array_merge($result, [
				'ok' => false,
				'error' => _('Der Dateiupload ist aufgrund eines Serverfehlers fehlgeschlagen.'),
			]);
		}

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
		static $assets = null;

		if (!$assets) {
			$assets = new Assets($this->request, $this->config);
		}

		return $assets;
	}
}
