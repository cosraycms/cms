<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Exception\RuntimeException;
use Cosray\Validation\Shapes;
use Cosray\Value\Color as ColorValue;
use Override;

class Color extends Field implements Capability\Selectable
{
	/** @var list<string|array{value: string, label: string}> */
	protected array $options = [];

	#[Override]
	public function control(): Control
	{
		return Control::color();
	}

	/**
	 * A palette offered beside the free input: hex colours, either as a
	 * list or keyed by hex with a name as value.
	 *
	 * @param array<string|array<string, mixed>> $options
	 */
	#[Override]
	public function options(array $options): void
	{
		$keyed = !array_is_list($options);
		$this->options = [];

		foreach ($options as $key => $option) {
			$this->options[] = self::paletteOption($keyed ? ['value' => (string) $key, 'label' => $option] : $option);
		}
	}

	/** @param string|array<string, mixed> $option */
	#[Override]
	public function add(string|array $option): void
	{
		$this->options[] = self::paletteOption($option);
	}

	/** @return list<string|array{value: string, label: string}> */
	#[Override]
	public function getOptions(): array
	{
		return $this->options;
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

	/** @return string|array{value: string, label: string} */
	private static function paletteOption(mixed $option): string|array
	{
		if (!is_array($option)) {
			return self::paletteColor($option);
		}

		$label = $option['label'] ?? null;

		if (!is_string($label)) {
			throw new RuntimeException('A labelled color option needs a string label');
		}

		return ['value' => self::paletteColor($option['value'] ?? null), 'label' => $label];
	}

	private static function paletteColor(mixed $value): string
	{
		return (
			ColorValue::normalize($value) ?? throw new RuntimeException(
				'Color options must be hex colors, got '
					. get_debug_type($value)
					. (is_string($value) ? " '{$value}'" : ''),
			)
		);
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
