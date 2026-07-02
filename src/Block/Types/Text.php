<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field\Control;
use Cosray\Value\Block;

final class Text extends Type
{
	public function id(): string
	{
		return 'text';
	}

	public function label(): string
	{
		return 'Einfacher Text';
	}

	public function control(): Control
	{
		return Control::blockText();
	}

	public function render(Block $block, RenderContext $ctx): string
	{
		return $ctx->value($block);
	}
}
