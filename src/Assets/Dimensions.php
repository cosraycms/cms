<?php

declare(strict_types=1);

namespace Cosray\Assets;

/** The pixel sizes of images as a visitor gets them. */
final class Dimensions
{
	/** The EXIF orientations that show an image on its side. */
	private const array SIDEWAYS = [5, 6, 7, 8];

	/**
	 * An image's size after its EXIF rotation, which renditions apply; null when
	 * the bytes are no image with readable dimensions.
	 *
	 * @return ?array{int, int}
	 */
	public static function of(string $contents): ?array
	{
		$stream = fopen('php://memory', 'r+');

		if ($stream === false) {
			return null;
		}

		fwrite($stream, $contents);
		rewind($stream);

		try {
			return self::read(static fn(): array|false => getimagesizefromstring($contents), $stream);
		} finally {
			fclose($stream);
		}
	}

	/**
	 * The same for a file, reading only its headers.
	 *
	 * @return ?array{int, int}
	 */
	public static function ofFile(string $path): ?array
	{
		return self::read(static fn(): array|false => getimagesize($path), $path);
	}

	/**
	 * @param callable(): (array<array-key, mixed>|false) $size
	 * @param resource|string $source
	 * @return ?array{int, int}
	 */
	private static function read(callable $size, mixed $source): ?array
	{
		// Both functions warn on undecodable input; that is an expected outcome.
		set_error_handler(static fn(): bool => true);

		try {
			$info = $size();

			if ($info === false) {
				return null;
			}

			[$width, $height, $type] = [(int) $info[0], (int) $info[1], $info[2]];

			return (
				$type === IMAGETYPE_JPEG && in_array(self::exifOrientation($source), self::SIDEWAYS, true)
					? [$height, $width]
					: [$width, $height]
			);
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * A JPEG's EXIF orientation, 1 to 8; 1 when it has none. Without the exif
	 * extension every image counts as upright.
	 */
	public static function orientation(string $path): int
	{
		set_error_handler(static fn(): bool => true);

		try {
			return self::exifOrientation($path);
		} finally {
			restore_error_handler();
		}
	}

	/** @param resource|string $source */
	private static function exifOrientation(mixed $source): int
	{
		if (!function_exists('exif_read_data')) {
			return 1;
		}

		$exif = exif_read_data($source);
		$orientation = is_array($exif) ? $exif['Orientation'] ?? 1 : 1;

		return is_int($orientation) && $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
	}
}
