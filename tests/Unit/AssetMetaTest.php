<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Assets\Meta;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class AssetMetaTest extends TestCase
{
	public function testKeepsLocalizedTextAndScalars(): void
	{
		$meta = Meta::apply(
			[],
			[
				'alt' => ['de' => ' Sudhaus ', 'en' => 'Brewhouse'],
				'title' => ['de' => 'Titel'],
				'caption' => ['de' => 'Bildunterschrift'],
				'credit' => ' Foto: M. Huber ',
				'focal' => ['x' => 0.25, 'y' => 0.75],
			],
			['de', 'en'],
			true,
		);

		$this->assertSame(
			[
				'alt' => ['de' => 'Sudhaus', 'en' => 'Brewhouse'],
				'title' => ['de' => 'Titel'],
				'caption' => ['de' => 'Bildunterschrift'],
				'credit' => 'Foto: M. Huber',
				'focal' => ['x' => 0.25, 'y' => 0.75],
			],
			$meta,
		);
	}

	public function testDropsEmptyValuesAndUnknownLocales(): void
	{
		$meta = Meta::apply(
			[],
			[
				'alt' => ['de' => '  ', 'en' => 'Only English', 'xx' => 'Rejected'],
				'title' => ['de' => ''],
				'credit' => '   ',
			],
			['de', 'en'],
			true,
		);

		$this->assertSame(['alt' => ['en' => 'Only English']], $meta);
	}

	public function testClearsManagedKeysButKeepsOthers(): void
	{
		$stored = [
			'alt' => ['de' => 'Alt'],
			'credit' => 'Old credit',
			'legacy' => 'keep me',
		];

		$meta = Meta::apply($stored, ['alt' => ['de' => 'New alt']], ['de'], false);

		$this->assertSame(['legacy' => 'keep me', 'alt' => ['de' => 'New alt']], $meta);
	}

	public function testClampsFocalAndIgnoresItForNonImages(): void
	{
		$image = Meta::apply([], ['focal' => ['x' => 1.4, 'y' => -0.2]], ['de'], true);
		$this->assertSame(['focal' => ['x' => 1.0, 'y' => 0.0]], $image);

		$file = Meta::apply([], ['focal' => ['x' => 0.5, 'y' => 0.5]], ['de'], false);
		$this->assertSame([], $file);
	}

	public function testToleratesMalformedInput(): void
	{
		$this->assertSame([], Meta::apply([], 'not-an-array', ['de'], true));
		$this->assertSame(
			[],
			Meta::apply([], ['alt' => 'string-not-map', 'focal' => 'nope'], ['de'], true),
		);
		$this->assertSame([], Meta::apply([], ['focal' => ['x' => 'a', 'y' => 0.5]], ['de'], true));
	}
}
