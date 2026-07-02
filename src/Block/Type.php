<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Field\Control;
use Cosray\Field\Field;
use Cosray\Value\Block;

/**
 * A block type usable inside a Blocks field.
 *
 * Provides the panel descriptor (control + initial payload) and the
 * server-side render for the frontend site.
 */
abstract class Type
{
	abstract public function id(): string;

	abstract public function label(): string;

	abstract public function control(): Control;

	abstract public function render(Block $block, RenderContext $ctx): string;

	/**
	 * Initial content payload used when the editor adds a block.
	 */
	public function init(): array
	{
		return [
			'type' => $this->id(),
			'colspan' => 12,
			'rowspan' => 1,
			'colstart' => null,
			'value' => [Field::NEUTRAL_LOCALE => ''],
		];
	}

	/**
	 * Hidden types render existing content but are not offered in the
	 * editor's add picker.
	 */
	public function hidden(): bool
	{
		return false;
	}
}
