<?php

declare(strict_types=1);

namespace Cosray\Value;

use Override;

class TranslatedImages extends Images
{
	#[Override]
	public function current(): TranslatedImage
	{
		return $this->get($this->pointer);
	}

	#[Override]
	public function get(int $index): TranslatedImage
	{
		return new TranslatedImage($this->owner, $this->field, $this->context, $index);
	}
}
