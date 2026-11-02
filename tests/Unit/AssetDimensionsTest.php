<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Assets;
use Cosray\Assets\Dimensions;
use Cosray\Assets\ResizeMode;
use Cosray\Assets\Size;
use Cosray\Assets\SizeSpec;
use Cosray\Assets\Transform;
use Cosray\Config;
use Cosray\Tests\TestCase;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssetDimensionsTest extends TestCase
{
	private string $root = '';

	protected function setUp(): void
	{
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/cosray-dimensions-' . bin2hex(random_bytes(6));
		mkdir($this->root . '/public/assets/ab/abcdefghijklm', 0o755, true);
		mkdir($this->root . '/public/cache', 0o755, true);
	}

	protected function tearDown(): void
	{
		$entries = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST,
		);

		foreach ($entries as $entry) {
			$entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
		}

		rmdir($this->root);

		parent::tearDown();
	}

	/** A JPEG with the given EXIF orientation, built without a camera. */
	public static function jpeg(int $width, int $height, int $orientation): string
	{
		$image = imagecreatetruecolor($width, $height);
		ob_start();
		imagejpeg($image);
		$jpeg = (string) ob_get_clean();
		$tiff = "II*\0" . pack('V', 8) . pack('v', 1) . pack('vvVvv', 0x0112, 3, 1, $orientation, 0) . pack('V', 0);
		$exif = "Exif\0\0" . $tiff;

		return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2);
	}

	public function testReadsAnImagesSize(): void
	{
		$image = imagecreatetruecolor(30, 20);
		ob_start();
		imagepng($image);

		$this->assertSame([30, 20], Dimensions::of((string) ob_get_clean()));
		$this->assertNull(Dimensions::of('<svg></svg>'));
		$this->assertNull(Dimensions::of(''));
	}

	/** @return iterable<string, array{int, array{int, int}}> */
	public static function orientations(): iterable
	{
		yield 'upright' => [1, [30, 20]];
		yield 'mirrored' => [2, [30, 20]];
		yield 'upside down' => [3, [30, 20]];
		yield 'mirrored and turned' => [5, [20, 30]];
		yield 'turned right' => [6, [20, 30]];
		yield 'turned and mirrored' => [7, [20, 30]];
		yield 'turned left' => [8, [20, 30]];
	}

	/** @param array{int, int} $expected */
	#[DataProvider('orientations')]
	public function testAppliesTheExifRotation(int $orientation, array $expected): void
	{
		$this->assertSame($expected, Dimensions::of(self::jpeg(30, 20, $orientation)));
	}

	#[DataProvider('orientations')]
	public function testAFileReadsTheSameAsItsBytes(int $orientation, array $expected): void
	{
		$path = "{$this->root}/photo.jpg";
		file_put_contents($path, self::jpeg(30, 20, $orientation));

		$this->assertSame($expected, Dimensions::ofFile($path));
	}

	public function testAFileThatIsNoImageHasNoSize(): void
	{
		$path = "{$this->root}/note.jpg";
		file_put_contents($path, 'no image');

		$this->assertNull(Dimensions::ofFile($path));
	}

	public function testARotatedPhotosSizeMatchesItsRendition(): void
	{
		$key = 'ab/abcdefghijklm/turned.jpg';
		$contents = self::jpeg(120, 60, 6);
		file_put_contents("{$this->root}/public/assets/{$key}", $contents);
		[$width, $height] = (array) Dimensions::of($contents);

		$this->assertSame(
			Dimensions::rendition($width, $height, new SizeSpec('small', ResizeMode::Width, 30)),
			$this->written($key, new SizeSpec('small', ResizeMode::Width, 30)),
		);
	}

	/** @return iterable<string, array{SizeSpec}> */
	public static function specs(): iterable
	{
		$specs = [
			['original', ResizeMode::Original, 1, null],
			['width', ResizeMode::Width, 37, null],
			['height', ResizeMode::Height, 23, null],
			['long', ResizeMode::LongSide, 41, null],
			['short', ResizeMode::ShortSide, 19, null],
			['fit', ResizeMode::Fit, 41, 11],
			['fit-tall', ResizeMode::Fit, 11, 41],
			['resize', ResizeMode::Resize, 33, 17],
			['crop', ResizeMode::Crop, 31, 29],
			['crop-square', ResizeMode::Crop, 40, null],
			['big-width', ResizeMode::Width, 900, null],
			['big-height', ResizeMode::Height, 900, null],
			['big-long', ResizeMode::LongSide, 900, null],
			['big-fit', ResizeMode::Fit, 900, 800],
			['big-resize', ResizeMode::Resize, 900, 20],
			['big-crop', ResizeMode::Crop, 900, 20],
		];

		foreach ($specs as [$name, $mode, $first, $second]) {
			yield $name => [new SizeSpec($name, $mode, $first, $second)];
			yield "{$name} enlarged" => [new SizeSpec($name, $mode, $first, $second, enlarge: true)];
		}
	}

	#[DataProvider('specs')]
	public function testMatchesWhatTheImageLibraryWrites(SizeSpec $spec): void
	{
		foreach ([[120, 60], [61, 37], [60, 120], [50, 50]] as [$width, $height]) {
			$key = "ab/abcdefghijklm/{$width}x{$height}.png";
			imagepng(imagecreatetruecolor($width, $height), "{$this->root}/public/assets/{$key}");

			$this->assertSame(
				$this->written($key, $spec),
				Dimensions::rendition($width, $height, $spec),
				"{$width}x{$height}",
			);
		}
	}

	public function testMatchesARenditionOfACrop(): void
	{
		$key = 'ab/abcdefghijklm/cropped.png';
		imagepng(imagecreatetruecolor(200, 100), "{$this->root}/public/assets/{$key}");
		$transform = new Transform(['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]);
		$part = $transform->region(200, 100);
		$spec = new SizeSpec('small', ResizeMode::Width, 40);

		$this->assertSame(
			$this->written($key, $spec, $transform),
			Dimensions::rendition($part['w'], $part['h'], $spec),
		);
	}

	public function testMatchesARenditionCutAroundAFocalPoint(): void
	{
		$key = 'ab/abcdefghijklm/focal.png';
		imagepng(imagecreatetruecolor(200, 100), "{$this->root}/public/assets/{$key}");
		$transform = new Transform(null, ['x' => 0.2, 'y' => 0.5]);

		foreach ([[40, 40], [300, 300]] as [$first, $second]) {
			$spec = new SizeSpec("focal{$first}", ResizeMode::Crop, $first, $second);
			$part = $transform->region(200, 100, $first / $second);

			$this->assertSame(
				$this->written($key, $spec, $transform),
				Dimensions::rendition($part['w'], $part['h'], $spec, framed: true),
			);
		}
	}

	/** @return array{int, int} */
	private function written(string $key, SizeSpec $spec, ?Transform $transform = null): array
	{
		$info = pathinfo($key);
		$name = bin2hex(random_bytes(4));
		new Assets(new Config($this->root))
			->image("/{$key}")
			->resize(new Size($spec->first, $spec->second), $spec->mode, $spec->enlarge, null, $name, $transform);
		$size = (array) getimagesize(
			"{$this->root}/public/cache/{$info['dirname']}/{$info['filename']}-{$name}.{$info['extension']}",
		);

		return [(int) $size[0], (int) $size[1]];
	}
}
