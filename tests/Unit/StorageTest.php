<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Storage\Storage;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Storage\Storage
 */
final class StorageTest extends TestCase
{
	private string $dir;

	protected function setUp(): void
	{
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/cosray-storage-' . bin2hex(random_bytes(4));
		mkdir("{$this->dir}/assets", 0o755, true);
	}

	protected function tearDown(): void
	{
		$this->removeDir($this->dir);
		parent::tearDown();
	}

	public function testKeyIsPerAssetDirWithSlug(): void
	{
		$this->assertSame(
			'wx/wxk3n7q2m5tbh/logo.jpg',
			Storage::key('wxk3n7q2m5tbh', 'logo.jpg'),
		);
		$this->assertSame(
			'wx/wxk3n7q2m5tbh/anderung-2.pdf',
			Storage::key('wxk3n7q2m5tbh', 'Änderung 2.PDF'),
		);
	}

	public function testKeyFallsBackToUidStem(): void
	{
		// Slug loses the stem entirely → the uid steps in.
		$this->assertSame(
			'wx/wxk3n7q2m5tbh/wxk3n7q2m5tbh.pdf',
			Storage::key('wxk3n7q2m5tbh', '☺.pdf'),
		);
		$this->assertSame(
			'wx/wxk3n7q2m5tbh/wxk3n7q2m5tbh',
			Storage::key('wxk3n7q2m5tbh', '☺'),
		);
	}

	public function testWriteExistsReadMoveDeleteRoundTrip(): void
	{
		$storage = $this->storage();

		$this->assertFalse($storage->exists('ab/abc123.txt'));

		$storage->write('ab/abc123.txt', 'payload');
		$this->assertTrue($storage->exists('ab/abc123.txt'));
		$this->assertSame('payload', $storage->read('ab/abc123.txt'));

		$storage->move('ab/abc123.txt', 'cd/cde456.txt');
		$this->assertFalse($storage->exists('ab/abc123.txt'));
		$this->assertSame('payload', $storage->read('cd/cde456.txt'));

		$storage->delete('cd/cde456.txt');
		$this->assertFalse($storage->exists('cd/cde456.txt'));
	}

	public function testPathResolvesToLocalFile(): void
	{
		$storage = $this->storage();
		$storage->write('ab/abc123.txt', 'payload');

		$path = $storage->path('ab/abc123.txt');

		$this->assertFileExists($path);
		$this->assertStringEndsWith('/assets/ab/abc123.txt', $path);
	}

	public function testPathRejectsMissingKey(): void
	{
		$this->expectException(RuntimeException::class);

		$this->storage()->path('no/such-file.txt');
	}

	public function testPathRejectsTraversal(): void
	{
		$storage = $this->storage();
		file_put_contents("{$this->dir}/outside.txt", 'secret');

		$this->expectException(RuntimeException::class);

		$storage->path('../outside.txt');
	}

	public function testDiskIsLocal(): void
	{
		$this->assertSame('local', $this->storage()->disk);
	}

	private function storage(): Storage
	{
		return new Storage($this->config(['path.public' => $this->dir]));
	}

	private function removeDir(string $dir): void
	{
		if (!is_dir($dir)) {
			return;
		}

		foreach (scandir($dir) ?: [] as $item) {
			if ($item === '.' || $item === '..') {
				continue;
			}

			$path = "{$dir}/{$item}";
			is_dir($path) && !is_link($path) ? $this->removeDir($path) : unlink($path);
		}

		rmdir($dir);
	}
}
