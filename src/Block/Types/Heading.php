<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field\Control;
use Cosray\Value\Block;

final class Heading extends Type
{
	public function __construct(
		private readonly int $level,
	) {}

	public function id(): string
	{
		return "h{$this->level}";
	}

	public function label(): string
	{
		return "Überschrift {$this->level}";
	}

	public function control(): Control
	{
		return Control::blockText();
	}

	public function hidden(): bool
	{
		return true;
	}

	public function render(Block $block, RenderContext $ctx): string
	{
		$value = $ctx->value($block);

		return "<h{$this->level}>{$value}</h{$this->level}>";
	}
}
