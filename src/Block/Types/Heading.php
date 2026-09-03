<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Contract\Block;
use Cosray\Field;
use Cosray\Schema\DefaultValue;
use Cosray\Schema\Label;
use Cosray\Schema\Options;
use Cosray\Schema\Required;
use Cosray\Schema\Translate;
use Cosray\Schema\Validate;
use Cosray\Value\Block as BlockValue;

#[Label('block:heading')]
final class Heading implements Block
{
	public const int DEFAULT_LEVEL = 2;

	#[Label('block:heading-text'), Required, Translate]
	protected Field\Text $text;

	// The option shape alone accepts any string, hence the rule.
	#[Label('block:heading-level'), Options(['1', '2', '3', '4', '5', '6'])]
	#[DefaultValue('2'), Validate('in:1,2,3,4,5,6')]
	protected Field\Option $level;

	public function render(BlockValue $block, RenderContext $ctx): string
	{
		$level = max(1, min(6, (int) $block->level->unwrap() ?: self::DEFAULT_LEVEL));

		return "<h{$level}>{$block->text}</h{$level}>";
	}
}
