<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Validation\Shapes;
use Celemas\Cms\Value;
use Celemas\Sire\Shape;

class Option extends Field implements Capability\Selectable
{
	use Capability\IsSelectable;

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
		$shape->add('type', 'string')->rules('required', 'in:option');

		$value = $shape->add('value', 'string')->rules(...$this->validators);

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		return $shape;
	}
}
