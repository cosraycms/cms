<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Validation\Prepare;
use Celemas\Cms\Validation\Shapes;
use Celemas\Cms\Value;
use Celemas\Sire\Shape;

class Image extends Field implements
	Capability\Translatable,
	Capability\File\Translatable,
	Capability\Limitable
{
	use Capability\IsLimitable;
	use Capability\IsTranslatable;
	use Capability\File\IsTranslatable;

	public function value(): Value\Images|Value\Image
	{
		if ($this->allowsMultipleItems()) {
			if ($this->translateFile) {
				return new Value\TranslatedImages($this->owner, $this, $this->valueContext);
			}

			return new Value\Images($this->owner, $this, $this->valueContext);
		}

		if ($this->translateFile) {
			return new Value\TranslatedImage($this->owner, $this, $this->valueContext);
		}

		return new Value\Image($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		if ($this->translateFile) {
			return $this->getTranslatableFileStructure('image', $value);
		}

		return $this->getFileStructure('image', $value);
	}

	public function shape(): Shape
	{
		$limitValidators = $this->limitValidators();
		$shape = Shapes::create()->title($this->label)->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:image');

		if ($this->translateFile) {
			// File-translatable: separate file arrays per locale
			$subShape = Shapes::list()->title($this->label)->keepUnknown();
			$subShape->add('file', 'text');
			$subShape->add('title', 'text');
			$subShape->add('alt', 'text');

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
			// Text-translatable: shared files but translatable titles and alt text
			$fileShape = Shapes::list()->keepUnknown();
			$fileShape->add('file', 'text', 'required');

			$locales = $this->owner->locales();
			$titleShape = Shapes::create()->title($this->label)->keepUnknown();
			$altShape = Shapes::create()->title($this->label)->keepUnknown();

			foreach ($locales as $locale) {
				$titleShape->add($locale->id, 'text');
				$altShape->add($locale->id, 'text');
			}

			$fileShape->add('title', $titleShape)->prepare(Prepare::nullAsEmpty(...));
			$fileShape->add('alt', $altShape)->prepare(Prepare::nullAsEmpty(...));
			$shape
				->add('files', $fileShape, ...$limitValidators, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			// Non-translatable
			$fileShape = Shapes::list()->keepUnknown();
			$fileShape->add('file', 'text', 'required');
			$fileShape->add('title', 'text');
			$fileShape->add('alt', 'text');
			$shape
				->add('files', $fileShape, ...$limitValidators, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		}

		return $shape;
	}
}
