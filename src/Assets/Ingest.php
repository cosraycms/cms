<?php

declare(strict_types=1);

namespace Cosray\Assets;

use Celema\Quma\Database;
use Cosray\Actor;
use Cosray\Config;
use Cosray\Exception\IngestError;
use Cosray\Exception\RuntimeException;
use Cosray\Storage\Storage;
use Cosray\Uid;
use Cosray\Util\Svg;
use finfo;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * Catalogs raw bytes as an asset: validation, SVG sanitising, hash dedup,
 * the storage write, and the catalog row. The upload controller and
 * importers share this one pipeline.
 */
final class Ingest
{
	private readonly Storage $storage;

	public function __construct(
		private readonly Config $config,
		private readonly Database $db,
	) {
		$this->storage = new Storage($config);
	}

	/** @param array<string, mixed> $meta */
	public function ingest(
		string $contents,
		string $filename,
		string $mediatype,
		?Actor $actor = null,
		array $meta = [],
	): IngestResult {
		$filename = self::safeFilename($filename);
		[$contents, $mime] = $this->prepared($contents, $filename, $mediatype);
		$hash = hash('sha256', $contents);
		$existing = $this->db
			->assets
			->byHash([
				'hash' => $hash,
				'disk' => $this->storage->disk,
			])
			->first();

		if ($existing) {
			return new IngestResult($existing, created: false);
		}

		[$width, $height] = $this->imageDimensions($mediatype, $contents);
		$uidConfig = $this->config->uid;
		$uid = new Uid($uidConfig->alphabet, $uidConfig->length)->generate();
		$key = Storage::key($uid, $filename);
		$this->storage->write($key, $contents);

		$row = [
			'uid' => $uid,
			'disk' => $this->storage->disk,
			'key' => $key,
			'filename' => $filename,
			'mime' => $mime,
			'bytes' => strlen($contents),
			'width' => $width,
			'height' => $height,
			'hash' => $hash,
			'meta' => $meta === [] ? '{}' : json_encode($meta),
			'creator' => ($actor ?? Actor::system())->id,
		];

		try {
			$this->db->assets->create($row)->one();
		} catch (Throwable $e) {
			$this->storage->delete($key);

			throw $e;
		}

		return new IngestResult($row, created: true);
	}

	/**
	 * An upload, without reading it into memory where nothing has to look inside:
	 * only images and SVGs get changed on the way in.
	 *
	 * @param array<string, mixed> $meta
	 */
	public function ingestUpload(
		UploadedFileInterface $file,
		string $mediatype,
		?Actor $actor = null,
		array $meta = [],
	): IngestResult {
		$filename = self::safeFilename((string) $file->getClientFilename());
		$path = self::localPath($file);

		if ($path === null || $this->holdsMetadata((string) new finfo(FILEINFO_MIME_TYPE)->file($path), $filename)) {
			return $this->ingest((string) $file->getStream(), $filename, $mediatype, $actor, $meta);
		}

		$mime = $this->validate(
			(string) new finfo(FILEINFO_MIME_TYPE)->file($path),
			(int) filesize($path),
			$filename,
			$mediatype,
		);
		$hash = (string) hash_file('sha256', $path);
		$existing = $this->db->assets->byHash(['hash' => $hash, 'disk' => $this->storage->disk])->first();

		if ($existing) {
			return new IngestResult($existing, created: false);
		}

		$uidConfig = $this->config->uid;
		$uid = new Uid($uidConfig->alphabet, $uidConfig->length)->generate();
		$key = Storage::key($uid, $filename);
		$this->writeFile($key, $path);

		$row = [
			'uid' => $uid,
			'disk' => $this->storage->disk,
			'key' => $key,
			'filename' => $filename,
			'mime' => $mime,
			'bytes' => (int) filesize($path),
			'width' => null,
			'height' => null,
			'hash' => $hash,
			'meta' => $meta === [] ? '{}' : json_encode($meta),
			'creator' => ($actor ?? Actor::system())->id,
		];

		try {
			$this->db->assets->create($row)->one();
		} catch (Throwable $e) {
			$this->storage->delete($key);

			throw $e;
		}

		return new IngestResult($row, created: true);
	}

	/** The mime type of an upload, without reading it into memory where it lies in a file. */
	public static function uploadMime(UploadedFileInterface $file): string
	{
		$path = self::localPath($file);
		$finfo = new finfo(FILEINFO_MIME_TYPE);

		return (string) ($path === null ? $finfo->buffer((string) $file->getStream()) : $finfo->file($path));
	}

	/**
	 * Puts new bytes under an asset, which keeps its uid and with it every use, and
	 * its texts. A new name moves it, and its renditions go. The new file must be
	 * of the same kind, as the fields using it asked for that kind.
	 *
	 * @param array<string, mixed> $row The asset's catalog row
	 * @return array<string, mixed> The row as it is now
	 * @throws IngestError
	 */
	public function replace(array $row, UploadedFileInterface $file): array
	{
		$kind = Asset::classify(isset($row['mime']) ? (string) $row['mime'] : null);
		$filename = self::safeFilename((string) $file->getClientFilename());
		$path = self::localPath($file);
		$streamed =
			$path !== null && !$this->holdsMetadata((string) new finfo(FILEINFO_MIME_TYPE)->file($path), $filename);
		$contents = '';

		if ($streamed) {
			$mime = $this->validate(
				(string) new finfo(FILEINFO_MIME_TYPE)->file($path),
				(int) filesize($path),
				$filename,
				$kind,
			);
		} else {
			[$contents, $mime] = $this->prepared((string) $file->getStream(), $filename, $kind);
		}

		if (Asset::classify($mime) !== $kind) {
			throw IngestError::disallowedType($mime);
		}

		$uid = (string) $row['uid'];
		$oldKey = (string) $row['key'];
		$hash = $streamed ? (string) hash_file('sha256', $path) : hash('sha256', $contents);
		$key = Storage::key($uid, $filename);

		// The same name would be the same address, which caches keep answering with the old file.
		if ($key === $oldKey) {
			$key = (string) preg_replace('/(\.[^.\/]*)?$/', '-' . substr($hash, 0, 8) . '$1', $key, 1);
		}

		[$width, $height] = $streamed ? [null, null] : $this->imageDimensions($kind, $contents);

		if ($streamed) {
			$this->writeFile($key, $path);
		} else {
			$this->storage->write($key, $contents);
		}

		$new = [
			'uid' => $uid,
			'key' => $key,
			'filename' => $filename,
			'mime' => $mime,
			'bytes' => $streamed ? (int) filesize($path) : strlen($contents),
			'width' => $width,
			'height' => $height,
			'hash' => $hash,
		];
		$this->db->assets->updateFile($new)->run();

		if ($key !== $oldKey) {
			$this->storage->delete($oldKey);
		}

		new Library($this->db, $this->config)->purgeRenditions($oldKey);

		return [...$row, ...$new];
	}

	/**
	 * What gets stored of an upload: checked, an SVG rebuilt, location data removed.
	 *
	 * @return array{string, string} the contents and the detected mime type
	 */
	private function prepared(string $contents, string $filename, string $mediatype): array
	{
		$mime = $this->validate(
			(string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents),
			strlen($contents),
			$filename,
			$mediatype,
		);

		// SVGs are served from the site origin, so a stored `<script>` or
		// `onload` would run there. Clean the markup before it lands in the
		// pool; hash and byte count are taken from the sanitized bytes.
		if ($mime === 'image/svg+xml' || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg') {
			$clean = self::sanitizeSvgMarkup($contents);

			if ($clean === null) {
				throw IngestError::unsafeSvg();
			}

			$contents = $clean;
		}

		return [Location::removed($contents, $mime), $mime];
	}

	/** Whether what is stored of a file differs from what came in, which takes reading it. */
	private function holdsMetadata(string $mime, string $filename): bool
	{
		return str_starts_with($mime, 'image/') || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg';
	}

	private static function localPath(UploadedFileInterface $file): ?string
	{
		$uri = $file->getStream()->getMetadata('uri');

		return is_string($uri) && is_file($uri) ? $uri : null;
	}

	private function writeFile(string $key, string $path): void
	{
		$stream = fopen($path, 'rb');

		if ($stream === false) {
			throw new RuntimeException('Could not read the uploaded file');
		}

		try {
			$this->storage->writeStream($key, $stream);
		} finally {
			if (is_resource($stream)) {
				fclose($stream);
			}
		}
	}

	/** @return string the given mime type once it holds */
	private function validate(string $mime, int $bytes, string $filename, string $mediatype): string
	{
		$upload = $this->config->upload;
		$mimeTypes = match ($mediatype) {
			'file' => $upload->file,
			'image' => $upload->image,
			'video' => $upload->video,
			default => throw new RuntimeException('Media type not supported: ' . $mediatype),
		};

		if ($filename === '') {
			throw IngestError::unknownFilename();
		}

		$limit = $upload->maxSizeFor($mediatype);

		if ($bytes > $limit) {
			throw IngestError::tooLarge($bytes, $limit);
		}

		$allowedExtensions = $mimeTypes[$mime] ?? null;

		if (!$allowedExtensions) {
			throw IngestError::disallowedType($mime);
		}

		$ext = pathinfo($filename, PATHINFO_EXTENSION) ?: null;

		if (!$ext || !in_array(strtolower($ext), $allowedExtensions, true)) {
			throw IngestError::wrongExtension($ext, $allowedExtensions, $mime);
		}

		return $mime;
	}

	/** @return array{0: ?int, 1: ?int} */
	private function imageDimensions(string $mediatype, string $contents): array
	{
		if ($mediatype !== 'image') {
			return [null, null];
		}

		return Dimensions::of($contents) ?? [null, null];
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

	/**
	 * Rebuild SVG markup from an allowlist: no scripts, event handlers or
	 * remote references. Returns null when the markup is rejected: empty,
	 * malformed, not an SVG document, or declaring its own DTD entities.
	 */
	public static function sanitizeSvgMarkup(string $svg): ?string
	{
		return Svg::sanitize($svg);
	}
}
