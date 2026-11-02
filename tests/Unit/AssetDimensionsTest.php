<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Dimensions;
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
}
