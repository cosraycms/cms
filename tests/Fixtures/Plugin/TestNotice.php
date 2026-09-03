<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Textarea;
use Cosray\Schema\Label;
use Cosray\Schema\Required;
use Cosray\Value\Block as BlockValue;

#[Label('Notice')]
final class TestNotice implements Block
{
	#[Label('Notice'), Required]
	protected Textarea $text;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return '<aside class="notice">' . $block->text . '</aside>';
	}
}
