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
		$shape = Shapes::create();
		$shape->add('type', 'string')->rules('required', 'in:grid');
		$shape->add('columns', 'int')->rules('required');

		$itemShape = new GridItemValidator(list: true, title: $this->label, keepUnknown: true);

		if ($this->translate) {
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$i18nShape = Shapes::create();

			foreach ($locales as $locale) {
				$innerValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$innerValidators[] = 'required';
				}

				$localeField = $i18nShape
					->add($locale->id, $itemShape)
					->rules(...$innerValidators)
					->prepare(Prepare::nullAsEmpty(...));

				if (!in_array('required', $innerValidators, true)) {
					$localeField->optional()->nullable();
				}
			}

			$value = $shape
				->add('value', $i18nShape)
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			$value = $shape
				->add('value', $itemShape)
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		return $shape;
	}
}
