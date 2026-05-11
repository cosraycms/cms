<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

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
		$shape = new Shape()
			->title($this->label)
			->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:option');
		$shape->add('value', 'text', ...$this->validators);

		return $shape;
	}
}
