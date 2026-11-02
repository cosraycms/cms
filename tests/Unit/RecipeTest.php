<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Recipe;
use Cosray\Assets\ResizeMode;
use Cosray\Assets\Sizes;
use Cosray\Assets\SizeSpec;
use Cosray\Assets\Transform;
use Cosray\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * @covers \Cosray\Assets\Recipe
 */
final class RecipeTest extends TestCase
{
	/** @return iterable<string, array{int, int, SizeSpec, array{int, int}}> */
	public static function targets(): iterable
	{
		yield 'width' => [120, 60, new SizeSpec('s', ResizeMode::Width, 37), [37, 19]];
		yield 'height' => [120, 60, new SizeSpec('s', ResizeMode::Height, 23), [46, 23]];
		yield 'long side of a wide image' => [120, 60, new SizeSpec('s', ResizeMode::LongSide, 40), [40, 20]];
		yield 'long side of a tall image' => [60, 120, new SizeSpec('s', ResizeMode::LongSide, 40), [20, 40]];
		yield 'short side of a wide image' => [120, 60, new SizeSpec('s', ResizeMode::ShortSide, 19), [38, 19]];
		yield 'short side of a tall image' => [60, 120, new SizeSpec('s', ResizeMode::ShortSide, 19), [19, 38]];
		yield 'fit by height' => [120, 60, new SizeSpec('s', ResizeMode::Fit, 41, 11), [22, 11]];
		yield 'fit by width' => [60, 120, new SizeSpec('s', ResizeMode::Fit, 41, 200), [41, 82]];
		yield 'fit that is big enough' => [120, 60, new SizeSpec('s', ResizeMode::Fit, 900, 800), [120, 60]];
		yield 'fit enlarged' => [120, 60, new SizeSpec('s', ResizeMode::Fit, 900, 800, enlarge: true), [900, 450]];
		yield 'resize ignores the ratio' => [120, 60, new SizeSpec('s', ResizeMode::Resize, 33, 17), [33, 17]];
		yield 'resize beyond the image keeps it' => [
			120,
			60,
			new SizeSpec('s', ResizeMode::Resize, 900, 20),
			[120, 60],
		];
		yield 'width beyond the image keeps it' => [120, 60, new SizeSpec('s', ResizeMode::Width, 900), [120, 60]];
		yield 'width enlarged' => [120, 60, new SizeSpec('s', ResizeMode::Width, 900, enlarge: true), [900, 450]];
		yield 'crop' => [120, 60, new SizeSpec('s', ResizeMode::Crop, 31, 29), [31, 29]];
		yield 'crop of one number is square' => [120, 60, new SizeSpec('s', ResizeMode::Crop, 40), [40, 40]];
		yield 'crop beyond a side keeps that side' => [
			120,
			60,
			new SizeSpec('s', ResizeMode::Crop, 900, 20),
			[120, 20],
		];
		yield 'crop enlarged' => [120, 60, new SizeSpec('s', ResizeMode::Crop, 900, 20, enlarge: true), [900, 20]];
		yield 'a thin image keeps a pixel' => [1000, 1, new SizeSpec('s', ResizeMode::Width, 100), [100, 1]];
		yield 'the original' => [120, 60, Sizes::original(), [120, 60]];
	}

	/** @param array{int, int} $expected */
	#[DataProvider('targets')]
	public function testWorksOutTheSizeOfARendition(int $width, int $height, SizeSpec $spec, array $expected): void
	{
		$recipe = Recipe::for($width, $height, $spec);

		$this->assertSame($expected, [$recipe->width, $recipe->height]);
	}

	public function testAResizeTakesTheWholeImage(): void
	{
		$this->assertRegion([0, 0, 120, 60], Recipe::for(120, 60, new SizeSpec('s', ResizeMode::Width, 37)));
	}

	/** @return iterable<string, array{?float, array{int, int, int, int}}> */
	public static function positions(): iterable
	{
		yield 'centered by default' => [null, [30, 0, 60, 60]];
		yield 'at the start' => [0.0, [0, 0, 60, 60]];
		yield 'a quarter along' => [0.25, [15, 0, 60, 60]];
		yield 'at the end' => [1.0, [60, 0, 60, 60]];
	}

	/** @param array{int, int, int, int} $region */
	#[DataProvider('positions')]
	public function testACropCutsAlongTheSideWithTooMuch(?float $position, array $region): void
	{
		$this->assertRegion($region, Recipe::for(120, 60, new SizeSpec('s', ResizeMode::Crop, 30, 30, $position)));
	}

	public function testACropOfATallImageCutsItsHeight(): void
	{
		$this->assertRegion([0, 60, 60, 60], Recipe::for(60, 120, new SizeSpec('s', ResizeMode::Crop, 30, 30, 1.0)));
	}

	public function testAUsesCropComesBeforeTheSize(): void
	{
		$recipe = Recipe::for(
			200,
			100,
			new SizeSpec('s', ResizeMode::Width, 40),
			new Transform(['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.5]),
		);

		$this->assertRegion([20, 20, 100, 50], $recipe);
		$this->assertSame([40, 20], [$recipe->width, $recipe->height]);
	}

	public function testACropSizeCutsWithinAUsesCrop(): void
	{
		$recipe = Recipe::for(
			200,
			100,
			new SizeSpec('s', ResizeMode::Crop, 25, 25),
			new Transform(['x' => 0, 'y' => 0, 'w' => 0.5, 'h' => 0.5]),
		);

		$this->assertRegion([25, 0, 50, 50], $recipe);
	}

	public function testAFocalPointPlacesTheCut(): void
	{
		$recipe = Recipe::for(
			200,
			100,
			new SizeSpec('s', ResizeMode::Crop, 40, 40),
			new Transform(null, ['x' => 0.2, 'y' => 0.5]),
		);

		$this->assertRegion([20, 0, 100, 100], $recipe);
		$this->assertSame([40, 40], [$recipe->width, $recipe->height]);
	}

	public function testACutAroundAFocalPointKeepsItsRatioWhenItIsTooSmall(): void
	{
		$recipe = Recipe::for(
			200,
			100,
			new SizeSpec('s', ResizeMode::Crop, 300, 300),
			new Transform(null, ['x' => 0.2, 'y' => 0.5]),
		);

		$this->assertSame([100, 100], [$recipe->width, $recipe->height]);
	}

	public function testAFocalPointLeavesOtherSizesWhole(): void
	{
		$recipe = Recipe::for(
			200,
			100,
			new SizeSpec('s', ResizeMode::Width, 40),
			new Transform(null, ['x' => 0.2, 'y' => 0.5]),
		);

		$this->assertRegion([0, 0, 200, 100], $recipe);
	}

	public function testCarriesTheQuality(): void
	{
		$this->assertSame(70, Recipe::for(9, 9, new SizeSpec('s', ResizeMode::Width, 4, quality: 70))->quality);
		$this->assertNull(Recipe::for(9, 9, new SizeSpec('s', ResizeMode::Width, 4))->quality);
	}

	/** @param array{int, int, int, int} $expected */
	private function assertRegion(array $expected, Recipe $recipe): void
	{
		$this->assertSame($expected, [$recipe->x, $recipe->y, $recipe->w, $recipe->h]);
	}
}
