<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Value;

class Option extends Field implements Capability\Selectable
{
	use Capability\IsSelectable;

	public function control(): Control
	{
		return Control::option();
	}

	protected bool $hasLabel = false;

	public function value(): Value\Option
	{
		return new Value\Option($this->owner, $this, $this->valueContext);
	}

	public function properties(): array
	{
		$result = parent::properties();
		$result['hasLabel'] = $this->hasLabel;

		return $result;
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('option', $value);
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
