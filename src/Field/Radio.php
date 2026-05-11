<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Value\Str;
use Celemas\Sire\Shape;

class Radio extends Field
{
	public function value(): Str
	{
		return new Str($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('radio', $value);
	}

	public function shape(): Shape
	{
		$shape = new Shape()
			->title($this->label)
			->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:radio');
		$shape->add('value', 'text', ...$this->validators);

		return $shape;
	}
}
