<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;

#[Label('block:richtext'), Handle('richtext')]
final class RichText implements Block
{
	#[Label('block:richtext'), Required, Translate]
	protected Field\RichText $text;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return (string) $block->text;
	}
}
