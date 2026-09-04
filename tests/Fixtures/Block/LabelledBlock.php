<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Text;
use Cosray\Schema\Label;
use Cosray\Schema\Labels;
use Cosray\Value\Block as BlockValue;

/** One field, but it wants its label anyway. */
#[Label('Labelled'), Labels]
final class LabelledBlock implements Block
{
	#[Label('Caption')]
	protected Text $caption;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return "<p>{$block->caption}</p>";
	}
}
