<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Link;
use Cosray\Field\Option;
use Cosray\Field\Text;
use Cosray\Schema\DefaultValue;
use Cosray\Schema\Label;
use Cosray\Schema\Options;
use Cosray\Schema\Required;
use Cosray\Schema\Settings;
use Cosray\Schema\Width;
use Cosray\Value\Block as BlockValue;

/** The label on the canvas, its target and style in the settings dialog. */
#[Label('Action')]
final class ActionBlock implements Block
{
	#[Label('Caption'), Required]
	protected Text $caption;

	#[Label('Target'), Required, Settings]
	protected Link $target;

	#[Label('Style'), Settings, Width(50), DefaultValue('filled'), Options(['filled', 'outlined'])]
	protected Option $style;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return (string) $block->target->a($block->caption);
	}
}
