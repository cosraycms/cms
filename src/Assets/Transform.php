<?php

declare(strict_types=1);

namespace Cosray\Assets;

use SensitiveParameter;

/**
 * What a use of an image asks of a rendition besides its size: a crop and a
 * focal point, both as fractions of the image as displayed, so they hold for
 * every size and for a rotated JPEG.
 *
 * Its URL form is signed. Only URLs the server minted generate files, which
 * keeps the cache route's writes bounded by what is stored.
 */
final readonly class Transform
{
	private const int SCALE = 10000;
	private const float MIN_CROP = 0.01;
	private const int SIGNATURE_LENGTH = 16;

	/**
	 * @param ?array{x: float, y: float, w: float, h: float} $crop
	 * @param ?array{x: float, y: float} $focal Relative to the whole image, so another crop keeps it.
	 */
	public function __construct(
		public ?array $crop = null,
		public ?array $focal = null,
	) {}

	/** Null when neither value holds anything usable. */
	public static function from(mixed $crop, mixed $focal): ?self
	{
		$crop = self::readCrop($crop);
		$focal = self::readFocal($focal);

		return $crop === null && $focal === null ? null : new self($crop, $focal);
	}

	/** A crop that fits the image and keeps something of it. The full image asks for nothing. */
	public static function validCrop(mixed $value): bool
	{
		return is_array($value) && (self::readCrop($value) !== null || self::whole($value));
	}

	/**
	 * The name suffix of a rendition: empty when nothing here changes the file,
	 * as a focal point only does for a size that cuts.
	 */
	public function variant(string $uid, SizeSpec $spec, #[SensitiveParameter] string $secret): string
	{
		$recipe = $this->recipe($spec->mode);

		return $recipe === '' ? '' : "~{$recipe}~" . self::signature($uid, $spec->name, $recipe, $secret);
	}

	/** Reads a rendition's name suffix back; null unless the server signed it. */
	public static function verified(
		string $uid,
		string $size,
		string $recipe,
		string $signature,
		#[SensitiveParameter]
		string $secret,
	): ?self {
		if (!hash_equals(self::signature($uid, $size, $recipe, $secret), $signature)) {
			return null;
		}

		return self::parse($recipe);
	}

	/**
	 * The part of an image of the given size to render: the crop, or for a size
	 * of its own ratio the window around the focal point. The window moves by the
	 * meaning of CSS `object-position`, so a rendition agrees with a preview.
	 *
	 * @return array{x: int, y: int, w: int, h: int}
	 */
	public function region(int $width, int $height, ?float $ratio = null): array
	{
		$crop = $this->crop ?? ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 1.0];
		$x = $crop['x'] * $width;
		$y = $crop['y'] * $height;
		$w = $crop['w'] * $width;
		$h = $crop['h'] * $height;
		$focal = $this->focalInCrop();

		if ($ratio !== null && $focal !== null) {
			$windowW = min($w, $h * $ratio);
			$windowH = min($h, $w / $ratio);
			$x += ($w - $windowW) * $focal['x'];
			$y += ($h - $windowH) * $focal['y'];
			$w = $windowW;
			$h = $windowH;
		}

		$left = min((int) round($x), $width - 1);
		$top = min((int) round($y), $height - 1);

		return [
			'x' => $left,
			'y' => $top,
			'w' => max(1, min((int) round($w), $width - $left)),
			'h' => max(1, min((int) round($h), $height - $top)),
		];
	}

	/**
	 * The focal point relative to the crop, the image a visitor gets.
	 *
	 * @return ?array{x: float, y: float}
	 */
	public function focalInCrop(): ?array
	{
		if ($this->focal === null) {
			return null;
		}

		if ($this->crop === null) {
			return $this->focal;
		}

		return [
			'x' => self::unit(($this->focal['x'] - $this->crop['x']) / $this->crop['w']),
			'y' => self::unit(($this->focal['y'] - $this->crop['y']) / $this->crop['h']),
		];
	}

	private function recipe(ResizeMode $mode): string
	{
		$parts = [];

		if ($this->crop !== null) {
			$parts[] = 'c' . implode('.', array_map(self::scaled(...), array_values($this->crop)));
		}

		if ($this->focal !== null && $mode === ResizeMode::Crop) {
			$parts[] = 'f' . implode('.', array_map(self::scaled(...), array_values($this->focal)));
		}

		return implode('_', $parts);
	}

	private static function parse(string $recipe): ?self
	{
		$pattern = '/^(?:c(\d+)\.(\d+)\.(\d+)\.(\d+))?_?(?:f(\d+)\.(\d+))?$/';

		if ($recipe === '' || preg_match($pattern, $recipe, $match, PREG_UNMATCHED_AS_NULL) !== 1) {
			return null;
		}

		$value = static fn(int $at): ?float => $match[$at] === null ? null : (int) $match[$at] / self::SCALE;

		return self::from(
			$match[1] === null ? null : ['x' => $value(1), 'y' => $value(2), 'w' => $value(3), 'h' => $value(4)],
			$match[5] === null ? null : ['x' => $value(5), 'y' => $value(6)],
		);
	}

	private static function signature(
		string $uid,
		string $size,
		string $recipe,
		#[SensitiveParameter]
		string $secret,
	): string {
		return substr(hash_hmac('sha256', "{$uid}/{$size}/{$recipe}", $secret), 0, self::SIGNATURE_LENGTH);
	}

	private static function scaled(float $value): int
	{
		return (int) round($value * self::SCALE);
	}

	private static function unit(float $value): float
	{
		return max(0.0, min(1.0, $value));
	}

	/** @return ?array{x: float, y: float, w: float, h: float} */
	private static function readCrop(mixed $value): ?array
	{
		if (!is_array($value)) {
			return null;
		}

		foreach (['x', 'y', 'w', 'h'] as $key) {
			if (!is_numeric($value[$key] ?? null) || $value[$key] < 0 || $value[$key] > 1) {
				return null;
			}
		}

		$crop = [
			'x' => (float) $value['x'],
			'y' => (float) $value['y'],
			'w' => (float) $value['w'],
			'h' => (float) $value['h'],
		];
		$fits =
			($crop['x'] + $crop['w']) <= (1 + (1 / self::SCALE))
			&& ($crop['y'] + $crop['h']) <= (1 + (1 / self::SCALE));

		return $fits && !self::whole($crop) && $crop['w'] >= self::MIN_CROP && $crop['h'] >= self::MIN_CROP
			? $crop
			: null;
	}

	/** @param array<array-key, mixed> $crop */
	private static function whole(array $crop): bool
	{
		return ($crop['w'] ?? 0) >= 1 && ($crop['h'] ?? 0) >= 1;
	}

	/** @return ?array{x: float, y: float} */
	private static function readFocal(mixed $value): ?array
	{
		if (!is_array($value) || !is_numeric($value['x'] ?? null) || !is_numeric($value['y'] ?? null)) {
			return null;
		}

		return ['x' => self::unit((float) $value['x']), 'y' => self::unit((float) $value['y'])];
	}
}
