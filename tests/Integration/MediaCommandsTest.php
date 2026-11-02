<?php

declare(strict_types=1);

namespace Cosray\Tests\Integration;

use Celema\Console\Buffer;
use Celema\Console\Io;
use Cosray\Assets\Sizes;
use Cosray\Commands\CleanMedia;
use Cosray\Commands\MediaReport;
use Cosray\Commands\WarmMedia;
use Cosray\Config;
use Cosray\Tests\IntegrationTestCase;
use Override;

/**
 * @internal
 *
 * @covers \Cosray\Assets\RenditionCache
 * @covers \Cosray\Commands\CleanMedia
 * @covers \Cosray\Commands\MediaReport
 * @covers \Cosray\Commands\WarmMedia
 */
final class MediaCommandsTest extends IntegrationTestCase
{
	private const string UID = 'mediacommand1';
	private const string DIR = 'me/mediacommand1';

	private string $dir;

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/cosray-media-commands-' . bin2hex(random_bytes(4));
		mkdir("{$this->dir}/assets/" . self::DIR, 0o755, true);
		mkdir("{$this->dir}/cache", 0o755, true);
		imagepng(imagecreatetruecolor(200, 100), "{$this->dir}/assets/" . self::DIR . '/pic.png');
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, mime, bytes, width, height, hash, meta, creator)
			VALUES (:uid, 'local', :key, 'Pic.png', 'image/png', 1, 200, 100, 'commands', '{}', 1)",
			['uid' => self::UID, 'key' => self::DIR . '/pic.png'],
		)->run();
	}

	#[Override]
	protected function tearDown(): void
	{
		$this->removeDir($this->dir);

		parent::tearDown();
	}

	private function settings(): Config
	{
		return $this->config([
			'path.public' => $this->dir,
			'media.sizes' => ['small' => ['width' => 50], 'large' => ['width' => 120]],
		]);
	}

	private function renditionFile(string $size): string
	{
		$spec = $this->settings()->media->sizes->get($size);

		return "{$this->dir}/cache/" . self::DIR . "/pic-{$size}~{$spec->fingerprint()}.png";
	}

	public function testWarmGeneratesTheNamedSizes(): void
	{
		$buffer = new Buffer();

		$code = (new WarmMedia($this->settings(), $this->db()))(new Io($buffer), size: ['small', 'large']);

		$this->assertSame(0, $code);
		$this->assertFileExists($this->renditionFile('small'));
		$this->assertSame(120, getimagesize($this->renditionFile('large'))[0]);
		$this->assertFileDoesNotExist($this->renditionFile('thumb'));
		$this->assertStringContainsString('failed', $buffer->output());
	}

	public function testWarmRefusesAnUnknownSize(): void
	{
		$buffer = new Buffer();

		$this->assertSame(1, (new WarmMedia($this->settings(), $this->db()))(new Io($buffer), size: ['nope']));
		$this->assertFileDoesNotExist($this->renditionFile('small'));
	}

	public function testWarmReportsWhatFails(): void
	{
		file_put_contents("{$this->dir}/assets/" . self::DIR . '/pic.png', 'no image');
		$buffer = new Buffer();

		$code = (new WarmMedia($this->settings(), $this->db()))(new Io($buffer), size: ['small']);

		$this->assertSame(1, $code);
		$this->assertStringContainsString(self::UID, $buffer->errorOutput());
	}

	public function testCleanOnlyCountsWithoutBeingToldToRemove(): void
	{
		$stale = "{$this->dir}/cache/" . self::DIR . '/pic-small~00000000.png';
		mkdir(dirname($stale), 0o755, true);
		file_put_contents($stale, 'old');
		$buffer = new Buffer();

		(new CleanMedia($this->settings(), $this->db()))(new Io($buffer));

		$this->assertFileExists($stale);
		$this->assertStringContainsString('1 rendition(s) to remove', $buffer->output());
	}

	public function testCleanRemovesWhatNoUrlLeadsToAndKeepsTheRest(): void
	{
		(new WarmMedia($this->settings(), $this->db()))(new Io(new Buffer()), size: ['small']);
		$dir = "{$this->dir}/cache/" . self::DIR;
		$keep = $this->renditionFile('small');
		$variant = substr($keep, 0, -4) . '~c1.2.3.4~0123456789abcdef.png';
		$original = "{$dir}/pic-original~" . Sizes::original()->fingerprint() . '~c1.2.3.4~0123456789abcdef.png';
		$gone = [
			"{$dir}/pic-small~00000000.png",
			"{$dir}/pic-small.png",
			"{$dir}/pic-removed~00000000.png",
			"{$dir}/renamed-small~" . $this->settings()->media->sizes->get('small')->fingerprint() . '.png',
		];
		$elsewhere = "{$this->dir}/cache/no/nosuchasset00/old-small~00000000.png";
		mkdir(dirname($elsewhere), 0o755, true);

		foreach ([$variant, $original, $elsewhere, ...$gone] as $file) {
			file_put_contents($file, 'x');
		}

		(new CleanMedia($this->settings(), $this->db()))(new Io(new Buffer()), apply: true);

		foreach ($gone as $file) {
			$this->assertFileDoesNotExist($file);
		}

		$this->assertFileExists($keep);
		$this->assertFileExists($variant);
		$this->assertFileExists($original);
		$this->assertDirectoryDoesNotExist("{$this->dir}/cache/no");
	}

	public function testCleanTakesEverythingWhenTold(): void
	{
		(new WarmMedia($this->settings(), $this->db()))(new Io(new Buffer()), size: ['small']);

		(new CleanMedia($this->settings(), $this->db()))(new Io(new Buffer()), apply: true, all: true);

		$this->assertFileDoesNotExist($this->renditionFile('small'));
		$this->assertFileExists("{$this->dir}/assets/" . self::DIR . '/pic.png');
	}

	public function testReportNamesWhatIsWrong(): void
	{
		mkdir("{$this->dir}/assets/le/leftoverasset", 0o755, true);
		$this->db()->execute(
			"INSERT INTO cms.assets (uid, disk, key, filename, mime, bytes, width, height, hash, meta, creator)
			VALUES ('missingasset1', 'local', 'mi/missingasset1/gone.png', 'Gone.png', 'image/png', 1, 9000, 9000, 'gone', '{}', 1)",
		)->run();
		$buffer = new Buffer();

		$code = (new MediaReport($this->settings(), $this->db()))(new Io($buffer), memory: '256M');
		$output = $buffer->output();

		$this->assertSame(1, $code);
		$this->assertStringContainsString('missingasset1  Gone.png', $output);
		$this->assertStringContainsString('le/leftoverasset', $output);
		$this->assertStringContainsString('missingasset1  Gone.png  9000 × 9000', $output);
		$this->assertStringContainsString('Too large to resize with a memory limit of 256 MB: 1', $output);
	}

	public function testATidyLibraryReportsSuccess(): void
	{
		$this->db()->execute('DELETE FROM cms.assets WHERE uid <> :uid', ['uid' => self::UID])->run();
		$buffer = new Buffer();

		$this->assertSame(0, (new MediaReport($this->settings(), $this->db()))(new Io($buffer), memory: '256M'));
		$this->assertStringContainsString('Missing from the disk: 0', $buffer->output());
	}

	private function removeDir(string $dir): void
	{
		foreach (scandir($dir) ?: [] as $entry) {
			if ($entry !== '.' && $entry !== '..') {
				$path = "{$dir}/{$entry}";
				is_dir($path) ? $this->removeDir($path) : unlink($path);
			}
		}

		rmdir($dir);
	}
}
