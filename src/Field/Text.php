<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Value\Text as TextValue;

class Text extends Field implements Capability\Translatable
{
	use Capability\IsTranslatable;

	public function value(): TextValue
	{
		return new TextValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getTranslatableStructure('text', $value);
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$shape->add('type', 'string')->rules('required', 'in:text');

		if ($this->isTranslatable()) {
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$i18nShape = Shapes::create();

			foreach ($locales as $locale) {
				$localeValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				}

				$localeField = $i18nShape->add($locale->id, 'string')->rules(...$localeValidators);

				if (!in_array('required', $localeValidators, true)) {
					$localeField->optional()->nullable();
				}
			}

			$value = $shape->add('value', $i18nShape)->rules(...$this->validators);
		} else {
			$value = $shape->add('value', 'string')->rules(...$this->validators);
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		return $shape;
	}
}
