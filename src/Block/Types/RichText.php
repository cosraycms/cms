<?php

declare(strict_types=1);

namespace Cosray\Block\Types;

use Cosray\Block\RenderContext;
use Cosray\Block\Type;
use Cosray\Field\Control;
use Cosray\Field\Field;
use Cosray\Richtext\Envelope;
use Cosray\Richtext\OwnerResolver;
use Cosray\Richtext\Renderer;
use Cosray\Value\Block;

final class RichText extends Type
{
	public function id(): string
	{
		return 'richtext';
	}

	public function label(): string
	{
		return 'Formatierter Text';
	}

	public function control(): Control
	{
		return Control::blockRichtext();
	}

	public function init(): array
	{
		return [
			'type' => $this->id(),
			'colspan' => 12,
			'rowspan' => 1,
			'colstart' => null,
			'format' => Envelope::FORMAT,
			'version' => Envelope::VERSION,
			'value' => [Field::NEUTRAL_LOCALE => null],
		];
	}

	public function render(Block $block, RenderContext $ctx): string
	{
		if (Envelope::isStructured($block->data)) {
			$value = is_array($block->data['value'] ?? null) ? $block->data['value'] : [];
			$doc = $ctx->effective($value);

			return is_array($doc)
				? new Renderer(new OwnerResolver($ctx->owner))->render($doc)
				: '';
		}

		return $ctx->value($block);
	}
}
