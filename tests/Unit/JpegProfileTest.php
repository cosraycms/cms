<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\JpegProfile;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Assets\JpegProfile
 */
final class JpegProfileTest extends TestCase
{
	private string $file = '';

	protected function setUp(): void
	{
		parent::setUp();

		$this->file = sys_get_temp_dir() . '/cosray-profile-' . bin2hex(random_bytes(6)) . '.jpg';
	}

	protected function tearDown(): void
	{
		if (is_file($this->file)) {
			unlink($this->file);
		}

		parent::tearDown();
	}

	private function jpeg(string ...$bodies): string
	{
		ob_start();
		imagejpeg(imagecreatetruecolor(4, 4));
		$jpeg = (string) ob_get_clean();
		$segments = '';

		foreach ($bodies as $at => $body) {
			$icc = "ICC_PROFILE\0" . chr($at + 1) . chr(count($bodies)) . $body;
			$segments .= "\xFF\xE2" . pack('n', strlen($icc) + 2) . $icc;
		}

		return substr($jpeg, 0, 2) . $segments . substr($jpeg, 2);
	}

	public function testReadsEverySegmentOfALongProfile(): void
	{
		file_put_contents($this->file, $this->jpeg(str_repeat('a', 60000), str_repeat('b', 60000)));

		$segments = JpegProfile::read($this->file);

		$this->assertSame(120000 + (2 * 18), strlen($segments));
	}

	public function testAnSrgbProfileCountsAsNone(): void
	{
		file_put_contents($this->file, $this->jpeg('sRGB IEC61966-2.1'));
		$this->assertSame('', JpegProfile::read($this->file));

		file_put_contents($this->file, $this->jpeg("s\0R\0G\0B\0 in UTF-16"));
		$this->assertSame('', JpegProfile::read($this->file));
	}

	public function testAFileWithoutAProfileHasNone(): void
	{
		file_put_contents($this->file, $this->jpeg());
		$this->assertSame('', JpegProfile::read($this->file));

		file_put_contents($this->file, 'no image');
		$this->assertSame('', JpegProfile::read($this->file));
	}

	public function testInsertedSegmentsAreReadBackAndTheImageStays(): void
	{
		file_put_contents($this->file, $this->jpeg('Adobe RGB (1998)'));
		$segments = JpegProfile::read($this->file);
		$other = "{$this->file}.other";
		file_put_contents($other, $this->jpeg());

		JpegProfile::insert($other, $segments);

		$this->assertSame($segments, JpegProfile::read($other));
		$this->assertSame([4, 4], array_slice((array) getimagesize($other), 0, 2));
		unlink($other);
	}

	public function testInsertingNothingLeavesTheFile(): void
	{
		file_put_contents($this->file, 'untouched');

		JpegProfile::insert($this->file, '');

		$this->assertSame('untouched', file_get_contents($this->file));
	}
}
