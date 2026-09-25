<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Textarea;
use Cosray\Schema\Handle;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;

/** One translated, required plain text field: the plainest block there is. */
#[Label('Text'), Handle('text')]
final class TextBlock implements Block
{
	#[Label('Text'), Required, Translate]
	protected Textarea $text;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return nl2br((string) $block->text);
	}
}
