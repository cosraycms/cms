<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Placeholder;
use Cosray\Schema\Required;
use Cosray\Schema\Tool;
use Cosray\Schema\Tools;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;
use Override;

#[Label('block:richtext'), Handle('richtext')]
final class RichText implements Block
{
	#[Label('block:richtext'), Required, Translate, Placeholder('block:text-placeholder'), Tools(Tool::DEFAULT)]
	protected Field\RichText $text;

	#[Override]
	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return (string) $block->text;
	}
}
