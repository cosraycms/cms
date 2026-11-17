<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\References\OwnerResolver;
use Cosray\Richtext\Renderer;
use Override;

class RichLine extends RichText
{
	/** The line's HTML without a paragraph around it, escaped by construction. */
	#[Override]
	public function raw(): string
	{
		if (isset($this->value)) {
			return $this->value;
		}

		$doc = $this->doc();

		if ($doc === null) {
			return $this->value = '';
		}

		return $this->value = new Renderer(new OwnerResolver($this->owner))->inline($doc);
	}
}
