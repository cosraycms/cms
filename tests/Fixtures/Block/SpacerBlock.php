<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Block;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Option;
use Cosray\Schema\DefaultValue;
use Cosray\Schema\Label;
use Cosray\Schema\Options;
use Cosray\Schema\Settings;
use Cosray\Value\Block as BlockValue;

/** Nothing to show on the canvas: its one field sits in the dialog. */
#[Label('Spacer')]
final class SpacerBlock implements Block
{
	#[Label('Height'), Settings, DefaultValue('m'), Options(['s', 'm', 'l'])]
	protected Option $height;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return "<div class=\"spacer-{$block->height}\"></div>";
	}
}
