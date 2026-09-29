<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Validation\Validators;
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

		$value = $shape->add('value', $this->zxxShape('string', [...$this->validators, ...$this->optionRules()]));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}

	/**
	 * Restricts the value to the declared options. A field without options
	 * declares no domain and accepts any string.
	 *
	 * @return list<string>
	 */
	private function optionRules(): array
	{
		if ($this->options === []) {
			return [];
		}

		return [Validators::in(array_map(
			static fn(mixed $option): mixed => is_array($option) ? $option['value'] ?? '' : $option,
			$this->options,
		))];
	}
}
