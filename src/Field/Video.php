<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Validation\Prepare;
use Celemas\Cms\Validation\Shapes;
use Celemas\Cms\Value;
use Celemas\Sire\Shape;

class Video extends Field implements
	Capability\Limitable,
	Capability\File\Translatable,
	Capability\Translatable
{
	use Capability\IsLimitable;
	use Capability\File\IsTranslatable;
	use Capability\IsTranslatable;

	public function value(): Value\Video
	{
		if ($this->translateFile) {
			return new Value\Video($this->owner, $this, $this->valueContext);
		}

		return new Value\Video($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		if ($this->translateFile) {
			return $this->getTranslatableFileStructure('video', $value);
		}

		return $this->getFileStructure('video', $value);
	}

	public function shape(): Shape
	{
		$limitValidators = $this->limitValidators();
		$shape = Shapes::create()->title($this->label)->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:video');

		if ($this->translateFile) {
			// File-translatable: separate file arrays per locale
			$subShape = Shapes::list()->title($this->label)->keepUnknown();
			$subShape->add('file', 'text');
			$subShape->add('title', 'text');

			$i18nShape = Shapes::create()->title($this->label)->keepUnknown();
			$locales = $this->owner->locales();

			foreach ($locales as $locale) {
				$i18nShape
					->add($locale->id, $subShape, ...$limitValidators)
					->prepare(Prepare::nullAsEmpty(...));
			}

			$shape
				->add('files', $i18nShape, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} elseif ($this->translate) {
			// Text-translatable: shared files but translatable titles
			$fileShape = Shapes::list()->keepUnknown();
			$fileShape->add('file', 'text', 'required');

			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$titleShape = Shapes::create()->title($this->label)->keepUnknown();

			foreach ($locales as $locale) {
				$localeValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				}

				$titleShape->add($locale->id, 'text', ...$localeValidators);
			}

			$fileShape->add('title', $titleShape)->prepare(Prepare::nullAsEmpty(...));
			$shape
				->add('files', $fileShape, ...$limitValidators, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			// Non-translatable
			$fileShape = Shapes::list()->keepUnknown();
			$fileShape->add('file', 'text', 'required');
			$fileShape->add('title', 'text');
			$shape
				->add('files', $fileShape, ...$limitValidators, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		}

		return $shape;
	}
}
