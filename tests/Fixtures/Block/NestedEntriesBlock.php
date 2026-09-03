<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Entries;
use Cosray\Schema\Allows;
use Cosray\Schema\Label;
use Cosray\Tests\Fixtures\Node\TestEntry;
use Cosray\Value\Block as BlockValue;

/** Rejected at build time: a block type must not contain an entries field. */
#[Label('Nested Entries')]
final class NestedEntriesBlock implements Block
{
	#[Label('Inner'), Allows(TestEntry::class)]
	protected Entries $inner;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return '';
	}
}
