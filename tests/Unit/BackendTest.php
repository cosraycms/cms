<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Backend\Backend;
use Cosray\Assets\Backend\Gd;
use Cosray\Assets\Format;
use Cosray\Assets\Recipe;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use ErrorException;
use GdImage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What every backend owes a recipe. Another backend adds a row to `backends()`,
 * and can skip itself there where its library is missing.
 *
 * @internal
 *
 * @coversNothing
 */
final class BackendTest extends TestCase
{
	private const int RED = 0xFF0000;
	private const int GREEN = 0x00FF00;
	private const int BLUE = 0x0000FF;
	private const int WHITE = 0xFFFFFF;

	/** The corners as shown, top left to bottom right, of the quarters as stored, by EXIF orientation. */
	private const array SHOWN = [
		1 => [self::RED, self::GREEN, self::BLUE, self::WHITE],
		2 => [self::GREEN, self::RED, self::WHITE, self::BLUE],
		3 => [self::WHITE, self::BLUE, self::GREEN, self::RED],
		4 => [self::BLUE, self::WHITE, self::RED, self::GREEN],
		5 => [self::RED, self::BLUE, self::GREEN, self::WHITE],
		6 => [self::BLUE, self::RED, self::WHITE, self::GREEN],
		7 => [self::WHITE, self::GREEN, self::BLUE, self::RED],
		8 => [self::GREEN, self::WHITE, self::RED, self::BLUE],
	];

	private string $dir = '';

	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/cosray-backend-' . bin2hex(random_bytes(6));
		mkdir($this->dir);
	}

	protected function tearDown(): void
	{
		foreach (glob("{$this->dir}/*") ?: [] as $file) {
			unlink($file);
		}

		rmdir($this->dir);

		parent::tearDown();
	}

	/** @return iterable<string, array{Backend}> */
	public static function backends(): iterable
	{
		yield 'gd' => [new Gd()];
	}

	/** @return iterable<string, array{Backend, int}> */
	public static function orientations(): iterable
	{
		foreach (self::backends() as $name => [$backend]) {
			foreach (array_keys(self::SHOWN) as $orientation) {
				yield "{$name}, orientation {$orientation}" => [$backend, $orientation];
			}
		}
	}

	/** @return iterable<string, array{Backend, Format, int, ?int}> */
	public static function formats(): iterable
	{
		foreach (self::backends() as $name => [$backend]) {
			yield "{$name}, jpeg" => [$backend, Format::Jpeg, IMAGETYPE_JPEG, IMG_JPG];
			yield "{$name}, png" => [$backend, Format::Png, IMAGETYPE_PNG, IMG_PNG];
			yield "{$name}, webp" => [$backend, Format::Webp, IMAGETYPE_WEBP, IMG_WEBP];
			yield "{$name}, avif" => [$backend, Format::Avif, IMAGETYPE_AVIF, IMG_AVIF];
		}
	}

	#[DataProvider('orientations')]
	public function testAWholeImageShowsUpright(Backend $backend, int $orientation): void
	{
		$sideways = $orientation >= 5;
		[$width, $height] = $sideways ? [20, 40] : [40, 20];
		$recipe = new Recipe(0, 0, $width, $height, $sideways ? 10 : 20, $sideways ? 20 : 10);

		$image = $this->rendered($backend, $this->jpeg($orientation), $recipe);

		$this->assertSame([$recipe->width, $recipe->height], [imagesx($image), imagesy($image)]);
		$this->assertSame(self::SHOWN[$orientation], $this->corners($image));
	}

	#[DataProvider('orientations')]
	public function testAPartIsReadOffTheImageAsShown(Backend $backend, int $orientation): void
	{
		$sideways = $orientation >= 5;
		[$width, $height] = $sideways ? [10, 20] : [20, 10];

		foreach ([[0, 0], [$width, 0], [0, $height], [$width, $height]] as $at => [$x, $y]) {
			$image = $this->rendered(
				$backend,
				$this->jpeg($orientation),
				new Recipe($x, $y, $width, $height, $width, $height),
			);

			$this->assertSame(array_fill(0, 4, self::SHOWN[$orientation][$at]), $this->corners($image));
		}
	}

	#[DataProvider('backends')]
	public function testAPartIsScaledToItsSize(Backend $backend): void
	{
		$image = $this->rendered($backend, $this->png(), new Recipe(20, 0, 20, 10, 8, 4));

		$this->assertSame([8, 4], [imagesx($image), imagesy($image)]);
		$this->assertSame(array_fill(0, 4, self::GREEN), $this->corners($image));
	}

	#[DataProvider('backends')]
	public function testAnImageCanBeEnlarged(Backend $backend): void
	{
		$image = $this->rendered($backend, $this->png(), new Recipe(0, 0, 40, 20, 80, 40));

		$this->assertSame([80, 40], [imagesx($image), imagesy($image)]);
		$this->assertSame(self::SHOWN[1], $this->corners($image));
	}

	#[DataProvider('backends')]
	public function testKeepsTheFormatOfTheSource(Backend $backend): void
	{
		$sources = [
			IMAGETYPE_JPEG => $this->jpeg(1),
			IMAGETYPE_PNG => $this->png(),
			IMAGETYPE_WEBP => $this->encoded(
				imagewebp(...),
				imagecreatefromstring((string) file_get_contents($this->png())),
				'webp',
			),
			IMAGETYPE_GIF => $this->encoded(
				imagegif(...),
				imagecreatefromstring((string) file_get_contents($this->png())),
				'gif',
			),
		];

		foreach ($sources as $type => $source) {
			$target = "{$this->dir}/kept";
			$backend->render($source, $target, new Recipe(0, 0, 40, 20, 20, 10));

			$this->assertSame($type, getimagesize($target)[2]);
		}
	}

	#[DataProvider('formats')]
	public function testWritesTheFormatOfTheRecipe(Backend $backend, Format $format, int $type, int $gdType): void
	{
		$this->skipUnlessGdSupports($backend, $gdType);

		$target = "{$this->dir}/written";
		$backend->render($this->png(), $target, new Recipe(0, 0, 40, 20, 20, 10, format: $format));

		$this->assertSame($type, getimagesize($target)[2]);
		$this->assertSame(self::SHOWN[1], $this->corners($this->opened($target)));
	}

	#[DataProvider('backends')]
	public function testAQualityChangesTheFile(Backend $backend): void
	{
		$photo = imagecreatetruecolor(200, 200);

		for ($i = 0; $i < 4000; $i++) {
			imagesetpixel($photo, random_int(0, 199), random_int(0, 199), random_int(0, 0xFFFFFF));
		}

		$source = $this->encoded(imagepng(...), $photo, 'png');
		$file = function (int $quality) use ($backend, $source): int {
			$target = "{$this->dir}/quality-{$quality}";
			$backend->render($source, $target, new Recipe(0, 0, 200, 200, 200, 200, $quality, Format::Jpeg));

			return (int) filesize($target);
		};

		$this->assertLessThan($file(90), $file(20));
	}

	/** @return iterable<string, array{Backend, Format}> */
	public static function transparentFormats(): iterable
	{
		foreach (self::backends() as $name => [$backend]) {
			yield "{$name}, png" => [$backend, Format::Png];
			yield "{$name}, webp" => [$backend, Format::Webp];
		}
	}

	#[DataProvider('transparentFormats')]
	public function testAlphaSurvives(Backend $backend, Format $format): void
	{
		$target = "{$this->dir}/alpha";
		$backend->render($this->transparent(), $target, new Recipe(0, 0, 40, 20, 20, 10, format: $format));

		$this->assertHalfTransparent($this->opened($target));
	}

	#[DataProvider('backends')]
	public function testAPaletteImagesTransparentColorSurvives(Backend $backend): void
	{
		foreach (['png' => imagepng(...), 'gif' => imagegif(...)] as $ext => $encode) {
			$source = $this->encoded($encode, $this->paletted(), $ext);
			$target = "{$this->dir}/keyed";
			$backend->render($source, $target, new Recipe(0, 0, 40, 20, 20, 10));

			$this->assertHalfTransparent($this->opened($target));
		}
	}

	#[DataProvider('backends')]
	public function testAJpegPutsTransparencyOnWhite(Backend $backend): void
	{
		$target = "{$this->dir}/flat";
		$backend->render($this->transparent(), $target, new Recipe(0, 0, 40, 20, 20, 10, format: Format::Jpeg));

		$image = $this->opened($target);
		$this->assertSame(self::WHITE, $this->nearest($image, 1, 5));
		$this->assertSame(self::RED, $this->nearest($image, 18, 5));
	}

	#[DataProvider('backends')]
	public function testFailsPlainlyOnWhatItCannotRead(Backend $backend): void
	{
		$source = "{$this->dir}/note.png";
		file_put_contents($source, 'no image');

		$this->assertFails($backend, $source, "{$this->dir}/out");
	}

	#[DataProvider('backends')]
	public function testFailsPlainlyOnADamagedImage(Backend $backend): void
	{
		$source = "{$this->dir}/damaged.png";
		$bytes = (string) file_get_contents($this->png());
		file_put_contents($source, substr($bytes, 0, 40) . str_repeat("\0", 40));

		$this->assertFails($backend, $source, "{$this->dir}/out");
	}

	#[DataProvider('backends')]
	public function testFailsPlainlyWhereItCannotWrite(Backend $backend): void
	{
		$this->assertFails($backend, $this->png(), "{$this->dir}/missing/out");
	}

	private function assertFails(Backend $backend, string $source, string $target): void
	{
		set_error_handler(static function (int $level, string $message): bool {
			throw new ErrorException($message, 0, $level);
		}, E_ALL);

		try {
			$this->expectException(RuntimeException::class);
			$backend->render($source, $target, new Recipe(0, 0, 40, 20, 20, 10));
		} finally {
			restore_error_handler();
		}
	}

	private function skipUnlessGdSupports(Backend $backend, int $type): void
	{
		if ($backend instanceof Gd && !(imagetypes() & $type)) {
			$this->markTestSkipped('This build of GD lacks the format.');
		}
	}

	private function rendered(Backend $backend, string $source, Recipe $recipe): GdImage
	{
		$target = "{$this->dir}/rendered";
		$backend->render($source, $target, $recipe);

		return $this->opened($target);
	}

	private function opened(string $file): GdImage
	{
		$image = imagecreatefromstring((string) file_get_contents($file));
		$this->assertInstanceOf(GdImage::class, $image);

		return $image;
	}

	/** @return list<int> */
	private function corners(GdImage $image): array
	{
		$right = imagesx($image) - 2;
		$bottom = imagesy($image) - 2;

		return [
			$this->nearest($image, 1, 1),
			$this->nearest($image, $right, 1),
			$this->nearest($image, 1, $bottom),
			$this->nearest($image, $right, $bottom),
		];
	}

	private function nearest(GdImage $image, int $x, int $y): int
	{
		['red' => $red, 'green' => $green, 'blue' => $blue] = imagecolorsforindex($image, imagecolorat($image, $x, $y));
		$distance = static fn(int $color): int => (
			abs(($color >> 16) - $red) + abs((($color >> 8) & 0xFF) - $green) + abs(($color & 0xFF) - $blue)
		);
		$colors = [self::RED, self::GREEN, self::BLUE, self::WHITE];
		usort($colors, static fn(int $a, int $b): int => $distance($a) <=> $distance($b));

		return $colors[0];
	}

	private function assertHalfTransparent(GdImage $image): void
	{
		$middle = (int) (imagesy($image) / 2);

		$this->assertSame(127, imagecolorsforindex($image, imagecolorat($image, 1, $middle))['alpha']);
		$this->assertSame(0, imagecolorsforindex($image, imagecolorat($image, imagesx($image) - 2, $middle))['alpha']);
	}

	/** Red, green, blue and white quarters of 40 by 20. */
	private function quarters(): GdImage
	{
		$image = imagecreatetruecolor(40, 20);
		imagefilledrectangle($image, 0, 0, 19, 9, self::RED);
		imagefilledrectangle($image, 20, 0, 39, 9, self::GREEN);
		imagefilledrectangle($image, 0, 10, 19, 19, self::BLUE);
		imagefilledrectangle($image, 20, 10, 39, 19, self::WHITE);

		return $image;
	}

	private function png(): string
	{
		return $this->encoded(imagepng(...), $this->quarters(), 'png');
	}

	/** Transparent on the left and red on the right. */
	private function transparent(): string
	{
		$image = imagecreatetruecolor(40, 20);
		imagealphablending($image, false);
		imagesavealpha($image, true);
		imagefilledrectangle($image, 0, 0, 19, 19, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
		imagefilledrectangle($image, 20, 0, 39, 19, self::RED);

		return $this->encoded(imagepng(...), $image, 'png');
	}

	/** The same as a palette image with a transparent color. */
	private function paletted(): GdImage
	{
		$image = imagecreate(40, 20);
		imagecolortransparent($image, (int) imagecolorallocate($image, 0, 255, 0));
		imagefilledrectangle($image, 20, 0, 39, 19, (int) imagecolorallocate($image, 255, 0, 0));

		return $image;
	}

	private function jpeg(int $orientation): string
	{
		$file = "{$this->dir}/source-{$orientation}.jpg";
		ob_start();
		imagejpeg($this->quarters(), null, 100);
		$jpeg = (string) ob_get_clean();
		$tiff = "II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, $orientation, 0) . pack('V', 0);
		$exif = "Exif\0\0{$tiff}";
		file_put_contents(
			$file,
			substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2),
		);

		return $file;
	}

	/** @param callable(GdImage, string): bool $encode */
	private function encoded(callable $encode, GdImage $image, string $ext): string
	{
		$file = "{$this->dir}/source-" . bin2hex(random_bytes(4)) . ".{$ext}";
		$encode($image, $file);

		return $file;
	}
}
