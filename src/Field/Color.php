<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Value\Color as ColorValue;
use Override;

class Color extends Field
{
	#[Override]
	public function control(): Control
	{
		return Control::color();
	}

	#[Override]
	public function value(): ColorValue
	{
		return new ColorValue($this->owner, $this, $this->valueContext);
	}

	#[Override]
	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('color', $value);
	}

	#[Override]
	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);

		$value = $shape
			->add('value', $this->zxxShape('string', ['color', ...$this->validators]))
			->finalize(self::normalize(...));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}

	/** Stores the canonical form of whatever hex notation the rule accepted. */
	private static function normalize(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		$color = ColorValue::normalize($value[self::NEUTRAL_LOCALE] ?? null);

		return $color === null ? $value : [...$value, self::NEUTRAL_LOCALE => $color];
	}
}
