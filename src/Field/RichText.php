<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Value\RichText as RichTextValue;

class RichText extends Text
{
	public function value(): RichTextValue
	{
		return new RichTextValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getTranslatableStructure('richtext', $value);
	}
}
