<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field;
use Cosray\Field\Control;
use Cosray\Value\Block;

final class Video extends Type
{
	public function id(): string
	{
		return 'video';
	}

	public function label(): string
	{
		return 'Video';
	}

	public function control(): Control
	{
		return Control::blockVideo();
	}

	public function init(): array
	{
		return [
			'type' => $this->id(),
			'colspan' => 12,
			'rowspan' => 1,
			'colstart' => null,
			'value' => [],
		];
	}

	public function render(Block $block, RenderContext $ctx): string
	{
		return $ctx->valueObject(Field\Video::class, $ctx->media($block))->__toString();
	}
}
