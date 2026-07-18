<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Validation\Shapes;
use Cosray\Value\Youtube as YoutubeValue;

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
		$result = $this->getTranslatableStructure('youtube', $value);
		$result['meta']['aspectRatioX'] = [self::NEUTRAL_LOCALE => 16];
		$result['meta']['aspectRatioY'] = [self::NEUTRAL_LOCALE => 9];

		return $result;
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$this->addType($shape);

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
			$value = $shape->add('value', $this->zxxShape('string', $this->validators));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}
}
