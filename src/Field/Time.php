<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Value\Time as TimeValue;

class Time extends Field
{
	public function control(): Control
	{
		return Control::time();
	}

	public function value(): TimeValue
	{
		return new TimeValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('time', $value);
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
