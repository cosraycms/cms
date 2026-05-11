<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Assets\Size;
use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Tests\TestCase;

final class SizeTest extends TestCase
{
	public function testSizeWithSingleDimension(): void
	{
		$size = new Size(800);

		$this->assertSame(800, $size->firstDimension);
		$this->assertNull($size->secondDimension);
		$this->assertNull($size->cropMode);
	}

	public function testSizeWithTwoDimensions(): void
	{
		$size = new Size(800, 600);

		$this->assertSame(800, $size->firstDimension);
		$this->assertSame(600, $size->secondDimension);
		$this->assertNull($size->cropMode);
	}

	public function testSizeWithCropMode(): void
	{
		$size = new Size(800, 600, 1);

		$this->assertSame(800, $size->firstDimension);
		$this->assertSame(600, $size->secondDimension);
		$this->assertSame(1, $size->cropMode);
	}

	public function testSizeWithArrayCropMode(): void
	{
		$size = new Size(800, 600, ['x' => 100, 'y' => 100]);

		$this->assertSame(800, $size->firstDimension);
		$this->assertSame(600, $size->secondDimension);
		$this->assertSame(['x' => 100, 'y' => 100], $size->cropMode);
	}

	public function testSizeThrowsOnZeroFirstDimension(): void
	{
		$this->throws(RuntimeException::class, 'Assets error: width must be >= 1');
		new Size(0);
	}

	public function testSizeThrowsOnNegativeFirstDimension(): void
	{
		$this->throws(RuntimeException::class, 'Assets error: width must be >= 1');
		new Size(-1);
	}

	public function testSizeThrowsOnNegativeSecondDimension(): void
	{
		$this->throws(RuntimeException::class, 'Assets error: width must be >= 1');
		new Size(800, -1);
	}
}
