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
use enshrined\svgSanitize\Sanitizer;
use finfo;
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

	public function ingest(
		string $contents,
		string $filename,
		string $mediatype,
		?Actor $actor = null,
		array $meta = [],
	): IngestResult {
		$filename = self::safeFilename($filename);
		$mime = $this->validate($contents, $filename, $mediatype);

		// SVGs are served inline, so a stored `<script>`/`onload` would run
		// in the site origin. Clean the markup before it lands in the pool;
		// hash and byte count are taken from the sanitized bytes.
		if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'svg') {
			$clean = self::sanitizeSvgMarkup($contents);

			if ($clean === null) {
				throw IngestError::unsafeSvg();
			}

			$contents = $clean;
		}

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
			'kind' => $mediatype,
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

	/** @return string the detected mime type */
	private function validate(string $contents, string $filename, string $mediatype): string
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

		if (strlen($contents) > $upload->maxSize) {
			throw IngestError::tooLarge(strlen($contents), $upload->maxSize);
		}

		$mime = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($contents);
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
	 * Strip scripts, event handlers and remote references from SVG markup.
	 * Returns null when the sanitizer rejects the markup as malformed.
	 */
	public static function sanitizeSvgMarkup(string $svg): ?string
	{
		$clean = new Sanitizer()->sanitize($svg);

		return $clean === false ? null : $clean;
	}
}
