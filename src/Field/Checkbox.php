<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Schema\StateLabels;
use Cosray\Validation\Shapes;
use Cosray\Value\Boolean;

class Checkbox extends Field
{
	public bool $nullable = false;
	public ?StateLabels $stateLabels = null;

	public function control(): Control
	{
		$labels = [];

		foreach (['true', 'false', 'null'] as $state) {
			$label = $this->stateLabels?->{$state};

			if ($label !== null) {
				$labels[$state] = __($label);
			}
		}

		return Control::checkbox(nullable: $this->nullable)->prop('labels', $labels);
	}

	public function value(): Boolean
	{
		return new Boolean($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		// A nullable field must be able to clear a configured default.
		if ($value === null && (!$this->nullable || func_num_args() === 0)) {
			$value = $this->default;
		}

		if ($value === null && !$this->nullable) {
			$value = false;
		}

		return [
			'type' => $this::class,
			'value' => is_array($value) && array_key_exists(self::NEUTRAL_LOCALE, $value)
				? $value
				: [self::NEUTRAL_LOCALE => $value],
		];
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
