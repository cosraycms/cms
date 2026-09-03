<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;

#[Label('block:text')]
final class Text implements Block
{
	#[Label('block:text'), Required, Translate]
	protected Field\Textarea $text;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return nl2br((string) $block->text);
	}
}
