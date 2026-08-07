<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field\Control;
use Cosray\Value\Block;

final class Iframe extends Type
{
	public function id(): string
	{
		return 'iframe';
	}

	public function label(): string
	{
		return __('block:iframe');
	}

	public function control(): Control
	{
		return Control::blockIframe();
	}

	public function render(Block $block, RenderContext $ctx): string
	{
		return $ctx->value($block);
	}
}
