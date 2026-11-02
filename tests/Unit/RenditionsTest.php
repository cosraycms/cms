<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Asset;
use Cosray\Assets\Recipe;
use Cosray\Assets\Renditions;
use Cosray\Assets\ResizeMode;
use Cosray\Assets\SizeSpec;
use Cosray\Assets\Transform;
use Cosray\Config;
use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use ErrorException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @coversNothing
 */
final class RenditionsTest extends TestCase
{
	private const string DIR = 'ab/abcdefghijklm';

	private string $root = '';

	protected function setUp(): void
	{
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/cosray-assets-' . bin2hex(random_bytes(6));
		mkdir("{$this->root}/public/assets/" . self::DIR, 0o755, true);
		mkdir($this->root . '/public/cache', 0o755, true);

		$image = imagecreatetruecolor(120, 60);
		imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));
		imagepng($image, "{$this->root}/public/assets/" . self::DIR . '/pic.png');
	}

	protected function tearDown(): void
	{
		$this->removeDir($this->root);

		parent::tearDown();
	}

	private function asset(string $file = 'pic.png'): Asset
	{
		return new Asset('abcdefghijklm', 'local', self::DIR . "/{$file}", $file);
	}

	private function renditions(): Renditions
	{
		return new Renditions(new Config($this->root));
	}

	private function largeImage(): void
	{
		$image = imagecreatetruecolor(4000, 4000);
		imagejpeg($image, "{$this->root}/public/assets/" . self::DIR . '/large.jpg');
		unset($image);
	}

	/**
	 * The error handler turns every notice into an exception in production,
	 * so a deprecation inside the resize pipeline aborts the request and
	 * leaves the rendition unwritten. Renditions must materialize with the
	 * strictest handler installed.
	 */
	public function testWritesARenditionWithAStrictErrorHandler(): void
	{
		$path = '';

		$this->withStrictErrorHandler(function () use (&$path): void {
			$path = $this->renditions()->render(
				$this->asset(),
				'pic-thumb.png',
				new SizeSpec('thumb', ResizeMode::Width, 60),
			);
		});

		$this->assertSame(realpath($this->root) . '/public/cache/' . self::DIR . '/pic-thumb.png', $path);
		$this->assertSame([60, 30], array_slice((array) getimagesize($path), 0, 2));
	}

	public function testResizesAPhotoOfAnOrdinaryCamera(): void
	{
		$this->largeImage();

		$path = $this->renditions()->render(
			$this->asset('large.jpg'),
			'large-thumb.jpg',
			new SizeSpec('thumb', ResizeMode::Width, 100),
		);

		$this->assertFileExists($path);
	}

	public function testRefusesAnImageTooLargeForTheMemoryLimit(): void
	{
		$this->largeImage();
		$limit = (string) ini_get('memory_limit');
		// @mago-expect lint:no-ini-set
		ini_set('memory_limit', (string) (memory_get_usage(true) + (32 * 1024 * 1024)));

		try {
			$this->expectException(RuntimeException::class);
			$this->renditions()->render(
				$this->asset('large.jpg'),
				'large-thumb.jpg',
				new SizeSpec('thumb', ResizeMode::Width, 100),
			);
		} finally {
			// @mago-expect lint:no-ini-set
			ini_set('memory_limit', $limit);
		}
	}

	public function testRefusesAnUnknownBackend(): void
	{
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('`media.backend` must be `gd` or `imagick`.');

		new Renditions(new Config($this->root, settings: ['media.backend' => 'vips']));
	}

	public function testRendersWithTheConfiguredBackend(): void
	{
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('The extension is missing.');
		}

		$path = new Renditions(new Config($this->root, settings: ['media.backend' => 'imagick']))->render(
			$this->asset(),
			'pic-thumb.png',
			new SizeSpec('thumb', ResizeMode::Width, 60),
		);

		$this->assertSame([60, 30], array_slice((array) getimagesize($path), 0, 2));
	}

	public function testRefusesAFileThatIsNoImage(): void
	{
		file_put_contents("{$this->root}/public/assets/" . self::DIR . '/note.png', 'no image');

		$this->expectException(RuntimeException::class);

		$this->renditions()->render(
			$this->asset('note.png'),
			'note-thumb.png',
			new SizeSpec('thumb', ResizeMode::Width, 60),
		);
	}

	public function testKeepsACurrentRendition(): void
	{
		$spec = new SizeSpec('thumb', ResizeMode::Width, 60);
		$path = $this->renditions()->render($this->asset(), 'pic-thumb.png', $spec);
		file_put_contents($path, 'kept');
		touch($path, time() + 10);

		$this->renditions()->render($this->asset(), 'pic-thumb.png', $spec);

		$this->assertSame('kept', file_get_contents($path));
	}

	public function testReplacesARenditionOlderThanItsOriginal(): void
	{
		$spec = new SizeSpec('thumb', ResizeMode::Width, 60);
		$path = $this->renditions()->render($this->asset(), 'pic-thumb.png', $spec);
		file_put_contents($path, 'stale');
		touch($path, time() - 10);

		$this->renditions()->render($this->asset(), 'pic-thumb.png', $spec);

		$this->assertSame([60, 30], array_slice((array) getimagesize($path), 0, 2));
	}

	public function testAFailedRenditionLeavesNoTemporaryFile(): void
	{
		$dir = "{$this->root}/public/cache/" . self::DIR;
		// A directory where the rendition should go makes moving it fail.
		mkdir("{$dir}/pic-blocked.png", 0o755, true);

		try {
			$this->withStrictErrorHandler(function (): void {
				$this->renditions()->render(
					$this->asset(),
					'pic-blocked.png',
					new SizeSpec('blocked', ResizeMode::Width, 60),
				);
			});
			$this->fail('Expected the rendition to fail');
		} catch (ErrorException $e) {
			// rename() warns before it fails; the strict handler throws.
			$this->assertStringContainsString('rename', $e->getMessage());
		}

		$this->assertSame(['pic-blocked.png'], array_values(array_diff((array) scandir($dir), ['.', '..'])));
	}

	public function testCreatesTheCacheDirectoryWithAStrictErrorHandler(): void
	{
		$this->removeDir($this->root . '/public/cache');
		mkdir($this->root . '/public/cache');

		$this->withStrictErrorHandler(function (): void {
			$this->renditions()->render($this->asset(), 'pic-thumb.png', new SizeSpec('thumb', ResizeMode::Width, 60));
		});

		$this->assertFileExists("{$this->root}/public/cache/" . self::DIR . '/pic-thumb.png');
	}

	/** Release-based deployments link both directories in from outside the document root. */
	public function testFollowsSymlinkedDirectories(): void
	{
		$shared = $this->root . '/shared';
		mkdir($shared);
		rename($this->root . '/public/assets', $shared . '/assets');
		rename($this->root . '/public/cache', $shared . '/cache');
		symlink($shared . '/assets', $this->root . '/public/assets');
		symlink($shared . '/cache', $this->root . '/public/cache');

		$this->renditions()->render($this->asset(), 'pic-thumb.png', new SizeSpec('thumb', ResizeMode::Width, 60));

		$this->assertFileExists($shared . '/cache/' . self::DIR . '/pic-thumb.png');
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
	public function testAFileHasTheSizeItsRecipeSays(SizeSpec $spec): void
	{
		foreach ([[120, 60], [61, 37], [60, 120], [50, 50]] as [$width, $height]) {
			$this->assertWrites($width, $height, $spec);
		}
	}

	public function testACropHasTheSizeItsRecipeSays(): void
	{
		$this->assertWrites(
			200,
			100,
			new SizeSpec('small', ResizeMode::Width, 40),
			new Transform(['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]),
		);
	}

	public function testACutAroundAFocalPointHasTheSizeItsRecipeSays(): void
	{
		foreach ([[40, 40], [300, 300]] as [$first, $second]) {
			$this->assertWrites(
				200,
				100,
				new SizeSpec("focal{$first}", ResizeMode::Crop, $first, $second),
				new Transform(null, ['x' => 0.2, 'y' => 0.5]),
			);
		}
	}

	public function testARotatedPhotoHasTheSizeItsRecipeSays(): void
	{
		$file = 'turned.jpg';
		file_put_contents(
			"{$this->root}/public/assets/" . self::DIR . "/{$file}",
			AssetDimensionsTest::jpeg(120, 60, 6),
		);
		$spec = new SizeSpec('small', ResizeMode::Width, 30);

		$path = $this->renditions()->render($this->asset($file), 'turned-small.jpg', $spec);

		$recipe = Recipe::for(60, 120, $spec);
		$this->assertSame([$recipe->width, $recipe->height], array_slice((array) getimagesize($path), 0, 2));
	}

	private function assertWrites(int $width, int $height, SizeSpec $spec, ?Transform $transform = null): void
	{
		$file = "{$width}x{$height}.png";
		imagepng(imagecreatetruecolor($width, $height), "{$this->root}/public/assets/" . self::DIR . "/{$file}");
		$name = bin2hex(random_bytes(4)) . '.png';

		$path = $this->renditions()->render($this->asset($file), $name, $spec, $transform);

		$recipe = Recipe::for($width, $height, $spec, $transform);
		$this->assertSame(
			[$recipe->width, $recipe->height],
			array_slice((array) getimagesize($path), 0, 2),
			"{$width}x{$height}",
		);
	}

	/** Mirrors the request-time error handler of `celema/core`. */
	private function withStrictErrorHandler(callable $callback): void
	{
		set_error_handler(static function (
			int $level,
			string $message,
			string $file = '',
			int $line = 0,
		): bool {
			throw new ErrorException($message, 0, $level, $file, $line);
		}, E_ALL);

		try {
			$callback();
		} finally {
			restore_error_handler();
		}
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . '/' . $entry;

			is_dir($path) && !is_link($path) ? $this->removeDir($path) : unlink($path);
		}

		rmdir($dir);
	}
}
