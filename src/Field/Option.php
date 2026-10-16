<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Validation\Validators;
use Cosray\Value;
use Override;

class Option extends Field implements Capability\Selectable
{
	use Capability\IsSelectable;

	#[Override]
	public function control(): Control
	{
		return Control::option();
	}

	protected bool $hasLabel = false;

	#[Override]
	public function value(): Value\Option
	{
		return new Value\Option($this->owner, $this, $this->valueContext);
	}

	#[Override]
	public function properties(): array
	{
		$result = parent::properties();
		$result['hasLabel'] = $this->hasLabel;

		return $result;
	}

	#[Override]
	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('option', $value);
	}

	#[Override]
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

		return [Validators::in(array_values(array_map(
			static fn(mixed $option): string => (string) (is_array($option) ? $option['value'] ?? '' : $option),
			$this->options,
		)))];
	}
}
