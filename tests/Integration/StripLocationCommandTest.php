<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Commands\StripLocation;
use Cosray\Config;
use Cosray\Tests\IntegrationTestCase;
use Override;

/**
 * @internal
 *
 * @covers \Cosray\Commands\StripLocation
 */
final class StripLocationCommandTest extends IntegrationTestCase
{
	private const string UID = 'locatedasset1';
	private const string KEY = 'lo/locatedasset1/photo.jpg';

	private string $dir;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/cosray-strip-' . bin2hex(random_bytes(4));
		mkdir("{$this->dir}/assets/lo/locatedasset1", 0o755, true);
		mkdir("{$this->dir}/cache", 0o755, true);
		file_put_contents($this->file(), $this->jpeg());
		$this->db()->assets->create([
			'uid' => self::UID,
			'disk' => 'local',
			'key' => self::KEY,
			'filename' => 'Photo.jpg',
			'mime' => 'image/jpeg',
			'bytes' => strlen($this->jpeg()),
			'width' => 4,
			'height' => 2,
			'hash' => 'before',
			'meta' => '{}',
			'creator' => 1,
		])->one();
	}

	#[Override]
	protected function tearDown(): void
	{
		$this->removeDir($this->dir);

		parent::tearDown();
	}

	public function testOnlyListsWithoutBeingToldToRewrite(): void
	{
		$before = (string) file_get_contents($this->file());
		[$code, $output] = $this->command();

		$this->assertSame(0, $code);
		$this->assertStringContainsString(self::UID, $output);
		$this->assertStringContainsString('--apply', $output);
		$this->assertSame($before, file_get_contents($this->file()));
		$this->assertSame('before', $this->hash());
	}

	public function testRewritesTheFileAndItsHashWhenTold(): void
	{
		[$code] = $this->command(apply: true);
		$contents = (string) file_get_contents($this->file());

		$this->assertSame(0, $code);
		$this->assertStringNotContainsString('49,53.1N', $contents);
		$this->assertSame(hash('sha256', $contents), $this->hash());
		$this->assertSame([4, 2], array_slice((array) getimagesize($this->file()), 0, 2));

		[, $again] = $this->command();
		$this->assertStringNotContainsString(self::UID, $again);
	}

	public function testAMissingFileIsLeftOut(): void
	{
		unlink($this->file());

		[$code, $output] = $this->command(apply: true);

		$this->assertSame(0, $code);
		$this->assertStringNotContainsString(self::UID, $output);
		$this->assertSame('before', $this->hash());
	}

	/** @return array{int, string} */
	private function command(bool $apply = false): array
	{
		$buffer = new Buffer();
		$command = new StripLocation($this->config(['path.public' => $this->dir]), $this->db());

		return [$command(new Io($buffer), apply: $apply), $buffer->output()];
	}

	private function hash(): string
	{
		return (string) $this->db()->assets->byUid(['uid' => self::UID])->one()['hash'];
	}

	private function file(): string
	{
		return "{$this->dir}/assets/" . self::KEY;
	}

	private function jpeg(): string
	{
		ob_start();
		imagejpeg(imagecreatetruecolor(4, 2));
		$jpeg = (string) ob_get_clean();
		$xmp = '<rdf:Description exif:GPSLatitude="49,53.1N"/>';

		return substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($xmp) + 2) . $xmp . substr($jpeg, 2);
	}

	private function removeDir(string $dir): void
	{
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = "{$dir}/{$entry}";
			is_dir($path) ? $this->removeDir($path) : unlink($path);
		}

		rmdir($dir);
	}
}
