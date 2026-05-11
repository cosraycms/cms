<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Value\Decimal as DecimalValue;
use Celemas\Sire\Shape;

class Decimal extends Field
{
	public function value(): DecimalValue
	{
		return new DecimalValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('decimal', $value);
	}

	public function shape(): Shape
	{
		$shape = new Shape()
			->title($this->label)
			->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:decimal');
		$shape->add('value', 'text', ...$this->validators);

		return $shape;
	}
}
