<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Assets;
use Cosray\Assets\ResizeMode;
use Cosray\Assets\Size;
use Cosray\Config;
use Cosray\Tests\TestCase;
use ErrorException;
use Gumlet\ImageResize;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssetsImageTest extends TestCase
{
	private string $root = '';

	protected function setUp(): void
	{
		parent::setUp();

		$this->root = sys_get_temp_dir() . '/cosray-assets-' . bin2hex(random_bytes(6));
		mkdir($this->root . '/public/assets/ab/abcdefghijklm', 0o755, true);
		mkdir($this->root . '/public/cache', 0o755, true);

		$image = imagecreatetruecolor(120, 60);
		imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));
		imagepng($image, $this->root . '/public/assets/ab/abcdefghijklm/pic.png');
	}

	protected function tearDown(): void
	{
		$this->removeDir($this->root);

		parent::tearDown();
	}

	/**
	 * The error handler turns every notice into an exception in production,
	 * so a deprecation inside the resize pipeline aborts the request and
	 * leaves the rendition unwritten. Renditions must materialize with the
	 * strictest handler installed.
	 */
	public function testResizeWritesRenditionWithStrictErrorHandler(): void
	{
		$assets = new Assets(new Config($this->root));
		$file = $this->root . '/public/cache/ab/abcdefghijklm/pic-thumb.png';

		$this->withStrictErrorHandler(static function () use ($assets): void {
			$assets
				->image('/ab/abcdefghijklm/pic.png')
				->resize(new Size(60), ResizeMode::Width, false, null, 'thumb');
		});

		$this->assertFileExists($file);
		$this->assertSame([60, 30], array_slice((array) getimagesize($file), 0, 2));
	}

	public function testCropUsesConfiguredPosition(): void
	{
		$assets = new Assets(new Config($this->root));
		$file = $this->root . '/public/cache/ab/abcdefghijklm/pic-square.png';

		$this->withStrictErrorHandler(static function () use ($assets): void {
			$assets
				->image('/ab/abcdefghijklm/pic.png')
				->resize(
					new Size(40, 40, ImageResize::CROPTOP),
					ResizeMode::Crop,
					false,
					null,
					'square',
				);
		});

		$this->assertFileExists($file);
		$this->assertSame([40, 40], array_slice((array) getimagesize($file), 0, 2));
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

			is_dir($path) ? $this->removeDir($path) : unlink($path);
		}

		rmdir($dir);
	}
}
