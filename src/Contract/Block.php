<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Block\RenderContext;
use Cosray\Value\Block as BlockValue;

/**
 * A block type usable inside a Blocks field: a class whose Field
 * properties declare the block's schema, like an entry type, plus the
 * frontend render. Typed properties are schema declarations and are
 * never assigned; `render()` reads the values off the block value.
 */
interface Block
{
	public function render(BlockValue $block, RenderContext $ctx): string;
}
