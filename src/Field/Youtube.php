<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Value\Youtube as YoutubeValue;
use Celemas\Sire\Shape;

class Youtube extends Field implements Capability\Translatable, Capability\Limitable
{
	use Capability\IsTranslatable;
	use Capability\IsLimitable;

	public function value(): YoutubeValue
	{
		return new YoutubeValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		return $this->getSimpleStructure('youtube', $value);
	}

	public function shape(): Shape
	{
		$shape = new Shape()
			->title($this->label)
			->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:youtube');

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
