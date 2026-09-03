<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Value\Block as BlockValue;

/** The one block whose output is trusted editor input: embed code, raw. */
#[Label('block:iframe')]
final class Iframe implements Block
{
	#[Label('block:iframe'), Required]
	protected Field\Iframe $code;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return (string) $block->code;
	}
}
