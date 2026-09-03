<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Block\Layout;
use Cosray\Block\Types;
use Cosray\Exception\NoSuchProperty;
use Cosray\Field;
use Cosray\Field\Blocks;
use Cosray\Field\Services;
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
		$this->assertSame(['span' => 12, 'rows' => 1, 'indent' => 0], Layout::normalize(null, 12, 1)->array());
		$this->assertSame(['span' => 1, 'rows' => 1, 'indent' => 0], Layout::normalize(['span' => 6], 1, 1)->array());
		$this->assertSame(
			['span' => 6, 'rows' => 2, 'indent' => 3],
			Layout::normalize(['span' => '6', 'rows' => '2', 'indent' => '3'], 12, 2)->array(),
		);
		$this->assertSame(
			['span' => 2, 'rows' => 6, 'indent' => 10],
			Layout::normalize(['span' => 1, 'rows' => 99, 'indent' => 30], 12, 2)->array(),
		);
		$this->assertSame(
			['span' => 8, 'rows' => 1, 'indent' => 4],
			Layout::normalize(['span' => 8, 'rows' => -1, 'indent' => 9], 12, 1)->array(),
		);
		$this->assertSame(['span' => 12, 'rows' => 1, 'indent' => 0], Layout::normalize('junk', 12, 1)->array());
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
		$this->assertSame(['span' => 12, 'rows' => 1, 'indent' => 0], $block->layout()->array());
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
		$field->init(Services::withDefaults());
		$field->columns(12);

		return new Block(
			$owner,
			$field,
			new ValueContext('content', [...$data, 'type' => Types\Text::class]),
			Types\Text::class,
		);
	}
}
