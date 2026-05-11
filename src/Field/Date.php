<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Value\Date as DateValue;
use Celemas\Sire\Shape;

class Date extends Field
{
	public function value(): DateValue
	{
		return new DateValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('date', $value);
	}

	public function shape(): Shape
	{
		$shape = new Shape()
			->title($this->label)
			->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:date');
		$shape->add('value', 'text', ...$this->validators);

		return $shape;
	}
}
