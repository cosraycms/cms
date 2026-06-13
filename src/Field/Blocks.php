<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Schema\TranslateMode;
use Cosray\Validation\BlockValidator;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value\Blocks as BlocksValue;

class Blocks extends Field implements Capability\Translatable, Capability\Blocks\Resizable
{
	use Capability\IsTranslatable;
	use Capability\Blocks\IsResizable;

	public function __toString(): string
	{
		return 'Blocks Field';
	}

	/** @return list<TranslateMode> */
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Asymmetric];
	}

	public function value(): BlocksValue
	{
		return new BlocksValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$value = $value ?: $this->default;

		if (is_array($value)) {
			return [
				'type' => 'blocks',
				'columns' => $this->columns,
				'minCellWidth' => $this->minCellWidth,
				'value' => $value,
			];
		}

		$result = [
			'type' => 'blocks',
			'columns' => $this->columns,
			'minCellWidth' => $this->minCellWidth,
			'value' => [],
		];

		if ($this->isAsymmetricallyTranslated()) {
			foreach ($this->owner->locales() as $locale) {
				$result['value'][$locale->id] = [];
			}
		}

		return $result;
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$shape->add('type', 'string')->rules('required', 'in:blocks');
		$shape->add('columns', 'int')->rules('required');

		$itemShape = new BlockValidator(list: true, title: $this->label, keepUnknown: true);

		if ($this->isAsymmetricallyTranslated()) {
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
