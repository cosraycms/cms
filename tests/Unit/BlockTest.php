<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Block as Builtin;
use Cosray\Block\Layout;
use Cosray\Exception\NoSuchProperty;
use Cosray\Field;
use Cosray\Field\Blocks;
use Cosray\Tests\Fixtures\Block\TextBlock;
use Cosray\Tests\RichtextOwnerTestCase;
use Cosray\Value\Block;
use Cosray\Value\ValueContext;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlockTest extends RichtextOwnerTestCase
{
	public function testLayoutNormalizesAndClamps(): void
	{
		$this->assertSame(['colspan' => 12, 'rowspan' => 1], Layout::normalize(null, 12, 1)->array());
		$this->assertSame(['colspan' => 1, 'rowspan' => 1], Layout::normalize(['colspan' => 6], 1, 1)->array());
		$this->assertSame(
			['colspan' => 6, 'rowspan' => 2],
			Layout::normalize(['colspan' => '6', 'rowspan' => '2', 'indent' => '3'], 12, 2)->array(),
		);
		$this->assertSame(
			['colspan' => 2, 'rowspan' => 6],
			Layout::normalize(['colspan' => 1, 'rowspan' => 99], 12, 2)->array(),
		);
		$this->assertSame(
			['colspan' => 8, 'rowspan' => 1],
			Layout::normalize(['colspan' => 8, 'rowspan' => -1], 12, 1)->array(),
		);
		$this->assertSame(['colspan' => 12, 'rowspan' => 1], Layout::normalize('junk', 12, 1)->array());
	}

	public function testPlacedLayoutKeepsItsPositionInsideTheGrid(): void
	{
		$this->assertSame(
			['colspan' => 6, 'rowspan' => 1, 'col' => 7, 'row' => 3],
			Layout::normalize(['colspan' => 6, 'col' => '9', 'row' => '3'], 12, 1)->array(),
		);
		// Without both lines the block has no position yet.
		$this->assertSame(
			['colspan' => 6, 'rowspan' => 1],
			Layout::normalize(['colspan' => 6, 'col' => 3, 'row' => 0], 12, 1)->array(),
		);
	}

	public function testBlockReturnsMetaStyleAndId(): void
	{
		$block = $this->block([
			'meta' => [
				'class' => ['zxx' => 'hero'],
				'id' => ['zxx' => 'section'],
				'note' => ['zxx' => 'kept'],
			],
		]);

		$this->assertSame('hero', $block->styleClass());
		$this->assertSame('section', $block->elementId());
		$this->assertSame('kept', $block->meta('note'));
		$this->assertSame('fallback', $block->meta('missing', 'fallback'));
	}

	public function testBlockDefaultsToNullValues(): void
	{
		$block = $this->block([]);

		$this->assertNull($block->styleClass());
		$this->assertNull($block->elementId());
		$this->assertNull($block->uid());
		$this->assertSame(['colspan' => 12, 'rowspan' => 1], $block->layout()->array());
		$this->assertTrue($block->isset());
		$this->assertSame('', $block->text->unwrap());
	}

	public function testBlockToleratesMalformedFieldData(): void
	{
		$block = $this->block(['fields' => ['text' => 'junk']]);

		$this->assertSame('', $block->text->unwrap());
	}

	public function testBlockThrowsOnUnknownField(): void
	{
		$this->throws(NoSuchProperty::class, "Block doesn't have field 'missing'");

		$this->block([])->missing;
	}

	private function block(array $data): Block
	{
		$owner = $this->owner();
		$field = new Blocks('content', $owner, new ValueContext('content', []));
		$field->init(self::blockServices());
		$field->columns(12);

		return new Block(
			$owner,
			$field,
			new ValueContext('content', [...$data, 'type' => TextBlock::class]),
			TextBlock::class,
		);
	}
}
