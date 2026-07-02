<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Value\Date as DateValue;

class Date extends Field
{
	public function control(): Control
	{
		return Control::date();
	}

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
		$shape = Shapes::create();
		$this->addType($shape);

		$value = $shape->add('value', $this->zxxShape('string', $this->validators));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}
}
