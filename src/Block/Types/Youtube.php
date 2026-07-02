<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field;
use Cosray\Field\Control;
use Cosray\Value\Block;

final class Youtube extends Type
{
	public function id(): string
	{
		return 'youtube';
	}

	public function label(): string
	{
		return 'Youtube-Video';
	}

	public function control(): Control
	{
		return Control::blockYoutube();
	}

	public function init(): array
	{
		return [
			'type' => $this->id(),
			'colspan' => 12,
			'rowspan' => 1,
			'colstart' => null,
			'value' => [Field\Field::NEUTRAL_LOCALE => ''],
			'meta' => [
				'aspectRatioX' => [Field\Field::NEUTRAL_LOCALE => 16],
				'aspectRatioY' => [Field\Field::NEUTRAL_LOCALE => 9],
			],
		];
	}

	public function render(Block $block, RenderContext $ctx): string
	{
		return $ctx->valueObject(Field\Youtube::class, $block->data)->__toString();
	}
}
