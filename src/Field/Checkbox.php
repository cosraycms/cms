<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Schema\StateLabels;
use Cosray\Validation\Shapes;
use Cosray\Value\Boolean;

class Checkbox extends Field
{
	public ?StateLabels $stateLabels = null;

	public function control(): Control
	{
		$labels = [];

		foreach (['true', 'false'] as $state) {
			$label = $this->stateLabels?->{$state};

			if ($label !== null) {
				$labels[$state] = __($label);
			}
		}

		return Control::checkbox()->prop('labels', $labels);
	}

	public function value(): Boolean
	{
		return new Boolean($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('checkbox', $value);
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);

		$value = $shape->add('value', $this->zxxShape('bool', $this->validators));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}
}
