<?php

declare(strict_types=1);

namespace Cosray\Assets\Backend;

use Closure;
use Cosray\Assets\Dimensions;
use Cosray\Assets\Format;
use Cosray\Assets\Recipe;
use Cosray\Exception\RuntimeException;
use GdImage;
use Override;

/**
 * Renders with PHP's GD, which is available everywhere and decodes the whole
 * image into memory.
 *
 * @internal
 */
final class Gd implements Backend
{
	/** Measured peak of decoding and resizing a large JPEG, rounded up. */
	private const int BYTES_PER_PIXEL = 5;

	private const int JPEG_QUALITY = 85;
	private const int WEBP_QUALITY = 85;
	private const int AVIF_QUALITY = 60;
	private const int PNG_COMPRESSION = 6;

	/** The alpha, out of 127, from which a pixel counts as transparent in a GIF. */
	private const int GIF_SEE_THROUGH = 64;

	/** The first EXIF orientation that shows an image on its side. */
	private const int SIDEWAYS = 5;

	#[Override]
	public function render(string $source, string $target, Recipe $recipe): void
	{
		$info = self::quietly(static fn(): array|false => getimagesize($source));

		if ($info === false) {
			throw new RuntimeException('Assets error: not a readable image');
		}

		[$width, $height, $type] = [(int) $info[0], (int) $info[1], (int) $info[2]];
		$orientation = $type === IMAGETYPE_JPEG ? Dimensions::orientation($source) : 1;
		$sideways = $orientation >= self::SIDEWAYS;
		$this->afford(($width * $height) + ($recipe->width * $recipe->height * ($sideways ? 2 : 1)));

		$image = $this->decode($source, $type);
		$output = match ($recipe->format) {
			Format::Jpeg => IMAGETYPE_JPEG,
			Format::Png => IMAGETYPE_PNG,
			Format::Webp => IMAGETYPE_WEBP,
			Format::Avif => IMAGETYPE_AVIF,
			null => $type,
		};
		$keyed = $output === IMAGETYPE_GIF && imagecolortransparent($image) >= 0;
		$canvas = $this->canvas(
			$sideways ? $recipe->height : $recipe->width,
			$sideways ? $recipe->width : $recipe->height,
			$output,
		);
		[$x, $y, $w, $h] = $this->stored($recipe, $orientation, $width, $height);
		imagecopyresampled($canvas, $image, 0, 0, $x, $y, imagesx($canvas), imagesy($canvas), $w, $h);
		unset($image);

		$upright = $this->upright($canvas, $orientation);

		if ($keyed) {
			$this->keyTransparency($upright);
		}

		$this->encode($upright, $target, $output, $recipe->quality);
	}

	/** Above what is left of the memory limit, a request must fail, not die in a fatal error. */
	private function afford(int $pixels): void
	{
		$limit = ini_parse_quantity((string) ini_get('memory_limit'));

		if ($limit > 0 && ($pixels * self::BYTES_PER_PIXEL) > ($limit - memory_get_usage(true))) {
			throw new RuntimeException("Assets error: {$pixels} pixels do not fit the memory limit");
		}
	}

	private function decode(string $source, int $type): GdImage
	{
		$image = self::quietly(static fn(): GdImage|false => match ($type) {
			IMAGETYPE_JPEG => imagecreatefromjpeg($source),
			IMAGETYPE_PNG => imagecreatefrompng($source),
			IMAGETYPE_GIF => imagecreatefromgif($source),
			IMAGETYPE_WEBP => imagecreatefromwebp($source),
			IMAGETYPE_AVIF => function_exists('imagecreatefromavif') ? imagecreatefromavif($source) : false,
			default => false,
		});

		return $image ?: throw new RuntimeException('Assets error: could not decode the image');
	}

	/** An empty image of the size, ready for the format's idea of a background. */
	private function canvas(int $width, int $height, int $type): GdImage
	{
		$canvas = imagecreatetruecolor(max(1, $width), max(1, $height));

		if ($type === IMAGETYPE_JPEG) {
			imagefilledrectangle($canvas, 0, 0, $width, $height, 0xFFFFFF);

			return $canvas;
		}

		imagealphablending($canvas, false);
		imagesavealpha($canvas, true);
		imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));

		return $canvas;
	}

	/**
	 * The recipe's part of the image as the file stores it.
	 *
	 * @return array{int, int, int, int}
	 */
	private function stored(Recipe $recipe, int $orientation, int $width, int $height): array
	{
		['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h] = (array) $recipe;

		return match ($orientation) {
			2 => [$width - $x - $w, $y, $w, $h],
			3 => [$width - $x - $w, $height - $y - $h, $w, $h],
			4 => [$x, $height - $y - $h, $w, $h],
			5 => [$y, $x, $h, $w],
			6 => [$y, $height - $x - $w, $h, $w],
			7 => [$width - $y - $h, $height - $x - $w, $h, $w],
			8 => [$width - $y - $h, $x, $h, $w],
			default => [$x, $y, $w, $h],
		};
	}

	/** Turning the small result instead of the whole image saves a second copy of the photo. */
	private function upright(GdImage $image, int $orientation): GdImage
	{
		if ($orientation >= self::SIDEWAYS) {
			$turned = imagerotate($image, $orientation === 8 ? 90 : 270, 0);
			$image = $turned ?: throw new RuntimeException('Assets error: could not turn the image');
		}

		$flip = match ($orientation) {
			2, 5 => IMG_FLIP_HORIZONTAL,
			3 => IMG_FLIP_BOTH,
			4, 7 => IMG_FLIP_VERTICAL,
			default => null,
		};

		if ($flip !== null) {
			imageflip($image, $flip);
		}

		return $image;
	}

	/** A GIF has one transparent palette entry, so the see-through pixels all move to it. */
	private function keyTransparency(GdImage $image): void
	{
		$clear = [];

		for ($y = imagesy($image) - 1; $y >= 0; $y--) {
			for ($x = imagesx($image) - 1; $x >= 0; $x--) {
				if ((imagecolorat($image, $x, $y) >> 24) >= self::GIF_SEE_THROUGH) {
					$clear[] = [$x, $y];
				}
			}
		}

		imagetruecolortopalette($image, false, 255);
		$color = (int) imagecolorallocate($image, 0, 0, 0);
		imagecolortransparent($image, $color);

		foreach ($clear as [$x, $y]) {
			imagesetpixel($image, $x, $y, $color);
		}
	}

	private function encode(GdImage $image, string $target, int $type, ?int $quality): void
	{
		imageinterlace($image, $type === IMAGETYPE_JPEG);

		$written = self::quietly(static fn(): bool => match ($type) {
			IMAGETYPE_JPEG => imagejpeg($image, $target, $quality ?? self::JPEG_QUALITY),
			IMAGETYPE_PNG => imagepng($image, $target, self::PNG_COMPRESSION),
			IMAGETYPE_GIF => imagegif($image, $target),
			IMAGETYPE_WEBP => imagewebp($image, $target, $quality ?? self::WEBP_QUALITY),
			IMAGETYPE_AVIF => function_exists('imageavif')
				&& imageavif($image, $target, $quality ?? self::AVIF_QUALITY),
			default => false,
		});

		if (!$written) {
			throw new RuntimeException('Assets error: could not write the rendition');
		}
	}

	/**
	 * Damaged files make these functions warn; that is an expected outcome.
	 *
	 * @template T
	 * @param Closure(): T $call
	 * @return T
	 */
	private static function quietly(Closure $call): mixed
	{
		set_error_handler(static fn(): bool => true);

		try {
			return $call();
		} finally {
			restore_error_handler();
		}
	}
}
