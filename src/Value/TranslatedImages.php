<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

class TranslatedImages extends Images
{
	public function current(): TranslatedImage
	{
		return $this->get($this->pointer);
	}

	public function get(int $index): TranslatedImage
	{
		return new TranslatedImage($this->owner, $this->field, $this->context, $index);
	}
}
