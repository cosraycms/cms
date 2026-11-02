<?php

declare(strict_types=1);

namespace Cosray\Assets\Backend;

use Cosray\Assets\Dimensions;
use Cosray\Assets\Format;
use Cosray\Assets\JpegProfile;
use Cosray\Assets\Recipe;
use Cosray\Exception\RuntimeException;
use Imagick as Image;
use ImagickException;
use ImagickPixelException;
use Override;

/**
 * Renders with ImageMagick. It decodes a JPEG only as far as the rendition needs
 * and keeps the colour profile in every format. Its memory lies outside PHP, so
 * `memory_limit` only bounds it through the limits set here.
 *
 * @internal
 */
final class Imagick implements Backend
{
	/** Decoding a JPEG at twice the size it ends up with leaves enough to scale from. */
	private const int JPEG_HEADROOM = 2;

	/** Where memory ends ImageMagick goes on in a file, which should not fill the disk. */
	private const int DISK_LIMIT = 4 * 1024 * 1024 * 1024;

	private const int JPEG_QUALITY = 85;
	private const int WEBP_QUALITY = 85;
	private const int AVIF_QUALITY = 60;

	/** Its tens are the zlib level. */
	private const int PNG_COMPRESSION = 65;

	private const array CODERS = [
		IMAGETYPE_JPEG => 'JPEG',
		IMAGETYPE_PNG => 'PNG',
		IMAGETYPE_GIF => 'GIF',
		IMAGETYPE_WEBP => 'WEBP',
		IMAGETYPE_AVIF => 'AVIF',
	];

	public function __construct()
	{
		if (!extension_loaded('imagick')) {
			throw new RuntimeException('`media.backend` asks for imagick, but the extension is not loaded.');
		}
	}

	#[Override]
	public function render(string $source, string $target, Recipe $recipe): void
	{
		set_error_handler(static fn(): bool => true);

		try {
			$info = getimagesize($source);
		} finally {
			restore_error_handler();
		}

		$coder = $info === false ? null : self::CODERS[$info[2]] ?? null;

		if ($info === false || $coder === null) {
			throw new RuntimeException('Assets error: not a readable image');
		}

		[$width, $height, $type] = [(int) $info[0], (int) $info[1], (int) $info[2]];
		$orientation = $type === IMAGETYPE_JPEG ? Dimensions::orientation($source) : 1;
		$output = $recipe->format === null ? $coder : strtoupper($recipe->format->value);

		try {
			$this->limit();
			$image = new Image();

			if ($type === IMAGETYPE_JPEG) {
				$scale = max($recipe->width / $recipe->w, $recipe->height / $recipe->h) * self::JPEG_HEADROOM;
				$image->setOption('jpeg:size', (int) ceil($width * $scale) . 'x' . (int) ceil($height * $scale));
			}

			// The coder names the format: nothing in a file gets to choose another one.
			$image->readImage("{$coder}:{$source}" . ($type === IMAGETYPE_GIF ? '[0]' : ''));

			$sideways = $orientation >= 5;
			$factor = $image->getImageWidth() / $width;
			[$x, $y, $w, $h] = $recipe->stored($orientation, $width, $height);
			$image->cropImage(
				max(1, (int) round($w * $factor)),
				max(1, (int) round($h * $factor)),
				(int) round($x * $factor),
				(int) round($y * $factor),
			);
			$image->setImagePage(0, 0, 0, 0);
			$image->resizeImage(
				$sideways ? $recipe->height : $recipe->width,
				$sideways ? $recipe->width : $recipe->height,
				Image::FILTER_LANCZOS,
				1,
			);
			$this->upright($image, $orientation);
			$this->tidy($image);

			if ($output === 'JPEG' && $image->getImageAlphaChannel()) {
				$image->setImageBackgroundColor('white');
				$image->setImageAlphaChannel(Image::ALPHACHANNEL_REMOVE);
			}

			$this->encode($image, $output, $recipe->quality);
			$image->writeImage("{$output}:{$target}");
			$image->clear();
		} catch (ImagickException|ImagickPixelException $e) {
			throw new RuntimeException('Assets error: ' . $e->getMessage(), previous: $e);
		}
	}

	private function limit(): void
	{
		// Its OpenMP threads conflict with those of a threaded PHP.
		Image::setResourceLimit(Image::RESOURCETYPE_THREAD, 1);
		Image::setResourceLimit(Image::RESOURCETYPE_DISK, self::DISK_LIMIT);

		$limit = ini_parse_quantity((string) ini_get('memory_limit'));

		if ($limit > 0) {
			Image::setResourceLimit(Image::RESOURCETYPE_MEMORY, $limit);
			Image::setResourceLimit(Image::RESOURCETYPE_MAP, $limit);
		}
	}

	private function upright(Image $image, int $orientation): void
	{
		match ($orientation) {
			2 => $image->flopImage(),
			3 => $image->rotateImage('none', 180),
			4 => $image->flipImage(),
			5 => $image->transposeImage(),
			6 => $image->rotateImage('none', 90),
			7 => $image->transverseImage(),
			8 => $image->rotateImage('none', 270),
			default => null,
		};
	}

	/** Leaves only what shows: no camera data, and the colour profile if it says more than sRGB. */
	private function tidy(Image $image): void
	{
		$profile = $image->getImageProfiles('icc', true)['icc'] ?? '';
		$cmyk = $image->getImageColorspace() === Image::COLORSPACE_CMYK;

		if ($cmyk) {
			$image->transformImageColorspace(Image::COLORSPACE_SRGB);
		}

		$image->stripImage();
		// Stripping also tells its PNG writer to leave a profile out.
		$image->setImageArtifact('png:exclude-chunk', 'date,time');

		if (is_string($profile) && $profile !== '' && !$cmyk && !JpegProfile::isSrgb($profile)) {
			$image->setImageProfile('icc', $profile);
		}
	}

	private function encode(Image $image, string $output, ?int $quality): void
	{
		$image->setImageFormat($output);
		$image->setInterlaceScheme($output === 'JPEG' ? Image::INTERLACE_PLANE : Image::INTERLACE_NO);

		$setting = match ($output) {
			'JPEG' => $quality ?? self::JPEG_QUALITY,
			'WEBP' => $quality ?? self::WEBP_QUALITY,
			'AVIF' => $quality ?? self::AVIF_QUALITY,
			'PNG' => self::PNG_COMPRESSION,
			default => null,
		};

		if ($setting !== null) {
			$image->setImageCompressionQuality($setting);
		}
	}
}
