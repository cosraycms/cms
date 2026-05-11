<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Unit;

use Celemas\Cms\Tests\TestCase;
use Celemas\Cms\Value\GridItem;

/**
 * @internal
 *
 * @coversNothing
 */
final class GridItemTest extends TestCase
{
	public function testGridItemReturnsStyleAndId(): void
	{
		$item = new GridItem('text', ['class' => 'hero', 'id' => 'section']);

		$this->assertSame('hero', $item->styleClass());
		$this->assertSame('section', $item->elementId());
	}

	public function testGridItemDefaultsToNullValues(): void
	{
		$item = new GridItem('text', []);

		$this->assertNull($item->styleClass());
		$this->assertNull($item->elementId());
	}
}
