<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Blocks;
use Cosray\Schema\Label;
use Cosray\Value\Block as BlockValue;

/** Rejected at build time: a block type must not contain a blocks field. */
#[Label('Nested Blocks')]
final class NestedBlocksBlock implements Block
{
	#[Label('Inner')]
	protected Blocks $inner;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return '';
	}
}
