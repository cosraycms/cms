<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Util;
use Cosray\Tests\TestCase;

final class AnimatedGifTest extends TestCase
{
	private const string FRAME = "\x00\x21\xF9\x04";

	private string $file;

	protected function setUp(): void
	{
		$this->file = sys_get_temp_dir() . '/cosray-gif-' . bin2hex(random_bytes(6));
	}

	protected function tearDown(): void
	{
		if (is_file($this->file)) {
			unlink($this->file);
		}
	}

	public function testGifWithSeveralFramesIsAnimated(): void
	{
		file_put_contents($this->file, 'GIF89a' . self::FRAME . 'frame' . self::FRAME . 'frame');

		$this->assertTrue(Util::isAnimatedGif($this->file));
	}

	public function testGifWithOneFrameIsNotAnimated(): void
	{
		file_put_contents($this->file, 'GIF89a' . self::FRAME . 'frame');

		$this->assertFalse(Util::isAnimatedGif($this->file));
	}

	public function testOtherFormatsAreNotAnimated(): void
	{
		file_put_contents($this->file, "\x89PNG" . self::FRAME . self::FRAME);

		$this->assertFalse(Util::isAnimatedGif($this->file));
	}
}
