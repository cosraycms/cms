<?php

declare(strict_types=1);

namespace Cosray\Tests\Fixtures\Plugin;

use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field\Control;
use Cosray\Value\Block;

final class TestNotice extends Type
{
	public function id(): string
	{
		return 'test-notice';
	}

	public function label(): string
	{
		return 'Notice';
	}

	public function control(): Control
	{
		return Control::element('test-notice', 'test-plugin/controls.js');
	}

	public function render(Block $block, RenderContext $ctx): string
	{
		return '<aside class="notice">' . $ctx->value($block) . '</aside>';
	}
}
