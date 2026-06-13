<?php

declare(strict_types=1);

namespace Cosray\Tests\Unit;

use Cosray\Tests\TestCase;
use Cosray\Value\Block;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlockTest extends TestCase
{
	public function testBlockReturnsStyleAndId(): void
	{
		$item = new Block('text', ['class' => 'hero', 'id' => 'section']);

		$this->assertSame('hero', $item->styleClass());
		$this->assertSame('section', $item->elementId());
	}

	public function testBlockDefaultsToNullValues(): void
	{
		$item = new Block('text', []);

		$this->assertNull($item->styleClass());
		$this->assertNull($item->elementId());
	}
}
