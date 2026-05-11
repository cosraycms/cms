<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Value\DateTime as DateTimeValue;
use Celemas\Sire\Shape;

class DateTime extends Field
{
	public function value(): DateTimeValue
	{
		return new DateTimeValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('datetime', $value);
	}

	public function shape(): Shape
	{
		$shape = new Shape()
			->title($this->label)
			->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:datetime');
		$shape->add('value', 'text', ...$this->validators);

		return $shape;
	}
}
