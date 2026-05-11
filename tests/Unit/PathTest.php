<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Tests\TestCase;
use Celemas\Cms\Util\Path;

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
}
