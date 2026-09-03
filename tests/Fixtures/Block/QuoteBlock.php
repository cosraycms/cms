<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Text;
use Cosray\Field\Textarea;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Value\Block as BlockValue;

/** A plugin-style block type: two fields and a render. */
#[Label('Quote')]
final class QuoteBlock implements Block
{
	#[Label('Quote'), Required, Translate]
	protected Textarea $text;

	#[Label('Source')]
	protected Text $source;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return "<blockquote><p>{$block->text}</p><cite>{$block->source}</cite></blockquote>";
	}
}
