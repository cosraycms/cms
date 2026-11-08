<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field\Option;
use Cosray\Field\Text;
use Cosray\Schema\DefaultValue;
use Cosray\Schema\Editor;
use Cosray\Schema\Label;
use Cosray\Schema\Options;
use Cosray\Schema\Settings;
use Cosray\Value\Block as BlockValue;

/** Drawn on the canvas by the plugin's own panel template. */
#[Label('Callout'), Editor('test-plugin:callout')]
final class TestCallout implements Block
{
	#[Label('Text')]
	protected Text $text;

	#[Label('Tone'), Settings, DefaultValue('calm'), Options(['calm', 'loud'])]
	protected Option $tone;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		return "<p class=\"callout-{$block->tone}\">{$block->text}</p>";
	}
}
