<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Schema\Tool;
use Cosray\Validation\RichtextDoc;
use Cosray\Value\RichLine as RichLineValue;
use Override;

/**
 * One line of formatted text: a richtext document of a single paragraph,
 * with marks but no structure. It looks like a text input, formats through
 * a bubble over the selection and renders without a paragraph, so it fits
 * into a heading or a caption.
 */
class RichLine extends RichText
{
	#[Override]
	public function value(): RichLineValue
	{
		return new RichLineValue($this->owner, $this, $this->valueContext);
	}

	#[Override]
	public function properties(): array
	{
		return array_merge(parent::properties(), [
			'line' => true,
			'richtextClasses' => (object) [],
		]);
	}

	/**
	 * Only the tools that mark text, from the field's own list. Without one
	 * a line formats with `Tool::MINIMAL`: the project's `richtext.tools`
	 * describes a document's toolbar.
	 *
	 * @return list<string>
	 */
	#[Override]
	public function getTools(): array
	{
		return array_values(array_map(
			static fn(Tool $tool): string => $tool->value,
			array_filter($this->tools ?: Tool::MINIMAL, static fn(Tool $tool): bool => !$tool->structural()),
		));
	}

	#[Override]
	protected function docValidator(): RichtextDoc
	{
		return new RichtextDoc([], $this->owner->config()->richtext->styles, line: true);
	}
}
