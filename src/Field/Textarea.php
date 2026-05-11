<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Sire\Shape;

class Textarea extends Text implements Capability\Translatable
{
	use Capability\IsTranslatable;

	public function structure(mixed $value = null): array
	{
		return $this->getTranslatableStructure('textarea', $value);
	}

	public function shape(): Shape
	{
		$shape = new Shape()
			->title($this->label)
			->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:textarea');

		if ($this->translate) {
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$i18nShape = new Shape()
				->title($this->label)
				->keepUnknown();

			foreach ($locales as $locale) {
				$localeValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				}

				$i18nShape->add($locale->id, 'text', ...$localeValidators);
			}

			$shape->add('value', $i18nShape, ...$this->validators);
		} else {
			$shape->add('value', 'text', ...$this->validators);
		}

		return $shape;
	}
}
