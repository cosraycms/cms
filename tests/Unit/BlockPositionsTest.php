<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Field\Blocks;
use Cosray\Migration\BlockPositions;
use Cosray\Tests\Fixtures\Node\TestMediaDocument;
use Cosray\Tests\Fixtures\Node\TestNodeWithBlocks;
use Cosray\Tests\TestCase;

/**
 * @internal
 *
 * @covers \Cosray\Migration\BlockPositions
 */
final class BlockPositionsTest extends TestCase
{
	private const array GRID = ['blocks' => ['columns' => 12, 'min' => 2]];

	public function testReadsTheGridOfEveryBlocksFieldFromItsAttribute(): void
	{
		$this->assertSame(
			['contentBlocks' => ['columns' => 12, 'min' => 2]],
			BlockPositions::grids(TestMediaDocument::class),
		);
		// Without #[Columns] a blocks field is a one-column list.
		$this->assertSame(['blocks' => ['columns' => 1, 'min' => 1]], BlockPositions::grids(TestNodeWithBlocks::class));
	}

	public function testPlacesEveryRowWhereTheFlowPutItAndStoresTheGrid(): void
	{
		$content = BlockPositions::content(
			[
				'title' => ['type' => 'Text', 'value' => ['zxx' => 'kept']],
				'blocks' => [
					'type' => Blocks::class,
					'value' => [
						'en' => [
							self::row('a', ['colspan' => 6, 'rowspan' => 1, 'indent' => 0]),
							// Beside the first one there was room, but the flow never went back.
							self::row('b', ['colspan' => 7, 'rowspan' => 1, 'indent' => 5]),
							// Clamped as the readers clamped it: wider than the grid, too tall.
							self::row('c', ['colspan' => 14, 'rowspan' => 9, 'indent' => 3]),
						],
						'de' => [],
					],
				],
			],
			self::GRID,
		);

		$this->assertSame(['type' => 'Text', 'value' => ['zxx' => 'kept']], $content['title']);
		$this->assertSame(12, $content['blocks']['columns']);
		$this->assertSame(
			[
				['colspan' => 6, 'rowspan' => 1, 'col' => 1, 'row' => 1],
				['colspan' => 7, 'rowspan' => 1, 'col' => 6, 'row' => 2],
				['colspan' => 12, 'rowspan' => 6, 'col' => 1, 'row' => 3],
			],
			array_column($content['blocks']['value']['en'], 'layout'),
		);
		$this->assertSame([], $content['blocks']['value']['de']);
	}

	public function testDropsTheIndentOfASplitsBlocksAndPlacesTheSplit(): void
	{
		$rows = BlockPositions::rows(
			[
				self::row('a', ['colspan' => 4, 'rowspan' => 1, 'indent' => 0]),
				[
					'uid' => 's',
					'layout' => ['colspan' => 6, 'rowspan' => 2, 'indent' => 2],
					'blocks' => [
						self::row('b', ['colspan' => 3, 'rowspan' => 2, 'indent' => 0]),
						self::row('c', ['colspan' => 3, 'rowspan' => 2, 'indent' => 0]),
					],
				],
			],
			12,
			2,
		);

		$this->assertSame(['colspan' => 6, 'rowspan' => 2, 'col' => 7, 'row' => 1], $rows[1]['layout']);
		$this->assertSame(
			[['colspan' => 3, 'rowspan' => 2], ['colspan' => 3, 'rowspan' => 2]],
			array_column($rows[1]['blocks'], 'layout'),
		);
	}

	public function testKeepsAStoredGridAndPlacedListsAndCanRunAgain(): void
	{
		$stored = [
			'blocks' => [
				'type' => Blocks::class,
				'columns' => 6,
				'value' => ['zxx' => [
					self::row('a', ['colspan' => 6, 'rowspan' => 1, 'col' => 1, 'row' => 2]),
					self::row('b', ['colspan' => 3, 'rowspan' => 1]),
				]],
			],
		];
		$once = BlockPositions::content($stored, self::GRID);

		// The six-column grid it was placed on wins over the field's twelve.
		$this->assertSame(6, $once['blocks']['columns']);
		// A placed list stays placed; the row without a position is the readers' to place.
		$this->assertSame($stored['blocks']['value'], $once['blocks']['value']);
		$this->assertSame($once, BlockPositions::content($once, self::GRID));

		$flowed = BlockPositions::content([
			'blocks' => [
				'type' => Blocks::class,
				'value' => ['zxx' => [self::row('a', ['colspan' => 4, 'rowspan' => 1, 'indent' => 8])]],
			],
		], self::GRID);

		$this->assertSame($flowed, BlockPositions::content($flowed, self::GRID));
	}

	private static function row(string $uid, array $layout): array
	{
		return ['uid' => $uid, 'type' => 'Cosray\Block\Text', 'layout' => $layout, 'fields' => []];
	}
}
