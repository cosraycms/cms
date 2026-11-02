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
		// Both functions warn on undecodable input; that is an expected outcome.
		set_error_handler(static fn(): bool => true);

		try {
			$info = getimagesizefromstring($contents);

			if ($info === false) {
				return null;
			}

			return (
				$info[2] === IMAGETYPE_JPEG && self::sideways($contents)
					? [$info[1], $info[0]]
					: [$info[0], $info[1]]
			);
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * The size of a rendition of a source this large, as the image library writes it.
	 *
	 * @param bool $framed Whether the source is already cut to the ratio of a crop size
	 * @return array{int, int}
	 */
	public static function rendition(int $width, int $height, SizeSpec $spec, bool $framed = false): array
	{
		$first = $spec->first;
		$second = $spec->second ?? $first;
		$scaled = static fn(int $target, int $from, int $to): int => (int) round($to * ($target / $from));
		$resize = static fn(int $w, int $h): array => (
			!$spec->enlarge && ($w > $width || $h > $height) ? [$width, $height] : [$w, $h]
		);
		$byWidth = static fn(): array => $resize($first, $scaled($first, $width, $height));
		$byHeight = static fn(): array => $resize($scaled($first, $height, $width), $first);

		return match ($spec->mode) {
			ResizeMode::Original => [$width, $height],
			ResizeMode::Width => $byWidth(),
			ResizeMode::Height => $byHeight(),
			ResizeMode::LongSide => $height > $width ? $byHeight() : $byWidth(),
			ResizeMode::ShortSide => $height < $width ? $byHeight() : $byWidth(),
			ResizeMode::Resize => $resize($first, $second),
			ResizeMode::Fit => self::fit($width, $height, $first, $second, $spec->enlarge),
			ResizeMode::Crop => match (true) {
				$framed => $resize($first, $second),
				$spec->enlarge => [$first, $second],
				default => [min($first, $width), min($second, $height)],
			},
		};
	}

	/** @return array{int, int} */
	private static function fit(int $width, int $height, int $maxWidth, int $maxHeight, bool $enlarge): array
	{
		if (!$enlarge && $width <= $maxWidth && $height <= $maxHeight) {
			return [$width, $height];
		}

		$ratio = $height / $width;
		$fitted = (int) round($maxWidth * $ratio);
		[$w, $h] = $fitted > $maxHeight ? [(int) round($maxHeight / $ratio), $maxHeight] : [$maxWidth, $fitted];

		return !$enlarge && ($w > $width || $h > $height) ? [$width, $height] : [$w, $h];
	}

	private static function sideways(string $contents): bool
	{
		if (!function_exists('exif_read_data')) {
			return false;
		}

		$stream = fopen('php://memory', 'r+');

		if ($stream === false) {
			return false;
		}

		fwrite($stream, $contents);
		rewind($stream);
		$exif = exif_read_data($stream);
		fclose($stream);

		return is_array($exif) && in_array($exif['Orientation'] ?? 1, self::SIDEWAYS, true);
	}
}
