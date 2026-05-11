<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Validation\GridItemValidator;
use Celemas\Cms\Validation\Prepare;
use Celemas\Cms\Validation\Shapes;
use Celemas\Cms\Value\Grid as GridValue;
use Celemas\Sire\Shape;

class Grid extends Field implements Capability\Translatable, Capability\Grid\Resizable
{
	use Capability\IsTranslatable;
	use Capability\Grid\IsResizable;

	public function __toString(): string
	{
		return 'Grid Field';
	}

	public function value(): GridValue
	{
		return new GridValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$value = $value ?: $this->default;

		if (is_array($value)) {
			return [
				'type' => 'grid',
				'columns' => $this->columns,
				'minCellWidth' => $this->minCellWidth,
				'value' => $value,
			];
		}

		$result = [
			'type' => 'grid',
			'columns' => $this->columns,
			'minCellWidth' => $this->minCellWidth,
			'value' => [],
		];

		if ($this->translate) {
			foreach ($this->owner->locales() as $locale) {
				$result['value'][$locale->id] = [];
			}
		}

		return $result;
	}

	public function shape(): Shape
	{
		$shape = Shapes::create()->title($this->label)->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:grid');
		$shape->add('columns', 'int', 'required');

		$itemShape = new GridItemValidator(list: true, title: $this->label, keepUnknown: true);

		if ($this->translate) {
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$i18nShape = Shapes::create()->title($this->label)->keepUnknown();

			foreach ($locales as $locale) {
				$innerValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$innerValidators[] = 'required';
				}

				$i18nShape
					->add($locale->id, $itemShape, ...$innerValidators)
					->prepare(Prepare::nullAsEmpty(...));
			}

			$shape
				->add('value', $i18nShape, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			$shape
				->add('value', $itemShape, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		}

		return $shape;
	}
}
