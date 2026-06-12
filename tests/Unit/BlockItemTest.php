<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\Value\BlockItem;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlockItemTest extends TestCase
{
	public function testBlockItemReturnsStyleAndId(): void
	{
		$item = new BlockItem('text', ['class' => 'hero', 'id' => 'section']);

		$this->assertSame('hero', $item->styleClass());
		$this->assertSame('section', $item->elementId());
	}

	public function testBlockItemDefaultsToNullValues(): void
	{
		$item = new BlockItem('text', []);

		$this->assertNull($item->styleClass());
		$this->assertNull($item->elementId());
	}
}
