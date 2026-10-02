<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Exception\RuntimeException;
use Cosray\Tests\TestCase;
use Cosray\Util\Path;
use ErrorException;

final class PathTest extends TestCase
{
	public function testInsideReturnsRealPath(): void
	{
		$parent = __DIR__;
		$child = 'PathTest.php';

		$result = Path::inside($parent, $child);

		$this->assertSame(realpath(__DIR__ . '/PathTest.php'), $result);
	}

	public function testInsideWithDirectory(): void
	{
		$parent = dirname(__DIR__);
		$child = 'Unit';

		$result = Path::inside($parent, $child);

		$this->assertSame(realpath(__DIR__), $result);
	}

	public function testInsideWithNestedPath(): void
	{
		$parent = dirname(__DIR__);
		$child = 'Unit/PathTest.php';

		$result = Path::inside($parent, $child);

		$this->assertSame(realpath(__DIR__ . '/PathTest.php'), $result);
	}

	public function testInsideThrowsOnNonexistentParent(): void
	{
		$this->throws(RuntimeException::class, 'Parent directory does not exist.');
		Path::inside('/nonexistent/directory', 'file.txt');
	}

	public function testInsideThrowsOnNonexistentChild(): void
	{
		$this->throws(
			RuntimeException::class,
			'File or directory does not exist or is not in the expected location.',
		);
		Path::inside(__DIR__, 'nonexistent-file.txt');
	}

	public function testInsideThrowsOnPathOutsideParent(): void
	{
		$this->throws(
			RuntimeException::class,
			'File or directory does not exist or is not in the expected location.',
		);
		// Try to access a file outside the parent directory using ../
		Path::inside(__DIR__, '../nonexistent');
	}

	public function testInsideWithCheckIsFileReturnsPathForFile(): void
	{
		$parent = __DIR__;
		$child = 'PathTest.php';

		$result = Path::inside($parent, $child, true);

		$this->assertSame(realpath(__DIR__ . '/PathTest.php'), $result);
	}

	public function testInsideWithCheckIsFileThrowsOnDirectory(): void
	{
		$this->throws(RuntimeException::class, 'Path is not a file:');
		$parent = dirname(__DIR__);
		$child = 'Unit';
		Path::inside($parent, $child, true);
	}

	public function testEnsureDirectoryCreatesMissingParents(): void
	{
		$root = sys_get_temp_dir() . '/cosray-path-' . bin2hex(random_bytes(6));
		$dir = $root . '/a/b';

		try {
			Path::ensureDirectory($dir);
			Path::ensureDirectory($dir);

			$this->assertDirectoryExists($dir);
		} finally {
			rmdir($dir);
			rmdir($root . '/a');
			rmdir($root);
		}
	}

	/**
	 * mkdir() warns when it fails, as it does when a concurrent request
	 * created the directory first. The warning must not reach the request's
	 * error handler; only a directory that still does not exist fails.
	 */
	public function testEnsureDirectoryReportsFailureWithoutTheWarning(): void
	{
		$file = (string) tempnam(sys_get_temp_dir(), 'cosray-path');
		set_error_handler(static fn(int $level, string $message): never => throw new ErrorException($message));

		try {
			Path::ensureDirectory($file);
			$this->fail('Expected the directory to be rejected');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('Could not create directory', $e->getMessage());
		} finally {
			restore_error_handler();
			unlink($file);
		}
	}
}
