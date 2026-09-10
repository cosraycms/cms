<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\DateTime\Codec;
use Cosray\Validation\Shapes;
use Cosray\Value\DateTime as DateTimeValue;

class DateTime extends Field
{
	public function control(): Control
	{
		return Control::datetime();
	}

	public function value(): DateTimeValue
	{
		return new DateTimeValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('datetime', $value);
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);

		$value = $shape
			->add('value', $this->zxxShape('string', ['rfc3339', ...$this->validators]))
			->finalize(self::normalize(...));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}

	protected function metaShape(): Shape
	{
		$shape = parent::metaShape();
		$timezone = Shapes::create();
		$timezone->add(self::NEUTRAL_LOCALE, 'string')->rules('timezone')->optional()->nullable();
		$shape->add('timezone', $timezone)->optional()->nullable();

		return $shape;
	}

	private static function normalize(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		$datetime = $value[self::NEUTRAL_LOCALE] ?? null;

		if (!is_string($datetime) || $datetime === '') {
			return $value;
		}

		$normalized = Codec::normalize($datetime);

		return $normalized === null ? $value : [...$value, self::NEUTRAL_LOCALE => $normalized];
	}
}
