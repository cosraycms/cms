<?php

declare(strict_types=1);

namespace Cosray\Field;

class Textarea extends Text
{
	public function structure(mixed $value = null): array
	{
		return $this->getTranslatableStructure('textarea', $value);
	}
}
