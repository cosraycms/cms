<?php

declare(strict_types=1);

namespace Cosray\Assets;

/**
 * What a rendition takes from its source and what it becomes. It is worked out
 * without touching the image, so markup knows the size of a file not written yet.
 */
final readonly class Recipe
{
	private const float CENTER = 0.5;

	/**
	 * @param int $x The part of the source as displayed, after any EXIF rotation.
	 * @param int $width The size of the rendition.
	 */
	public function __construct(
		public int $x,
		public int $y,
		public int $w,
		public int $h,
		public int $width,
		public int $height,
		public ?int $quality = null,
	) {}

	/** @param int $width The size of the source as displayed, after any EXIF rotation. */
	public static function for(int $width, int $height, SizeSpec $spec, ?Transform $transform = null): self
	{
		$framed = $spec->mode === ResizeMode::Crop && $transform?->focal !== null;
		$part = $transform?->region($width, $height, $framed ? $spec->first / ($spec->second ?? $spec->first) : null)
			?? ['x' => 0, 'y' => 0, 'w' => $width, 'h' => $height];
		[$targetWidth, $targetHeight] = self::target($part['w'], $part['h'], $spec, $framed);

		if ($spec->mode === ResizeMode::Crop && !$framed) {
			$part = self::cut($part, $targetWidth / $targetHeight, $spec->pos ?? self::CENTER);
		}

		return new self(
			$part['x'],
			$part['y'],
			$part['w'],
			$part['h'],
			$targetWidth,
			$targetHeight,
			$spec->quality,
		);
	}

	/**
	 * @param bool $framed Whether the source is already cut to the ratio of a crop size
	 * @return array{int, int}
	 */
	private static function target(int $width, int $height, SizeSpec $spec, bool $framed): array
	{
		$first = $spec->first;
		$second = $spec->second ?? $first;
		$scaled = static fn(int $target, int $from, int $to): int => max(1, (int) round($to * ($target / $from)));
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
		$fitted = max(1, (int) round($maxWidth * $ratio));
		[$w, $h] = $fitted > $maxHeight
			? [max(1, (int) round($maxHeight / $ratio)), $maxHeight]
			: [$maxWidth, $fitted];

		return !$enlarge && ($w > $width || $h > $height) ? [$width, $height] : [$w, $h];
	}

	/**
	 * The window of a ratio within a part, placed along the side with too much.
	 *
	 * @param array{x: int, y: int, w: int, h: int} $part
	 * @param float $position From the start of that side, as a fraction of what is left over.
	 * @return array{x: int, y: int, w: int, h: int}
	 */
	private static function cut(array $part, float $ratio, float $position): array
	{
		if ($ratio < ($part['w'] / $part['h'])) {
			$window = max(1, min($part['w'], (int) round($part['h'] * $ratio)));
			$part['x'] += (int) round(($part['w'] - $window) * $position);
			$part['w'] = $window;

			return $part;
		}

		$window = max(1, min($part['h'], (int) round($part['w'] / $ratio)));
		$part['y'] += (int) round(($part['h'] - $window) * $position);
		$part['h'] = $window;

		return $part;
	}
}
