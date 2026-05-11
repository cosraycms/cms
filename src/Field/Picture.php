<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Validation\Prepare;
use Celemas\Cms\Validation\Shapes;
use Celemas\Cms\Value;
use Celemas\Sire\Shape;

class Picture extends Field implements
	Capability\Limitable,
	Capability\File\Translatable,
	Capability\Translatable
{
	use Capability\IsLimitable;
	use Capability\File\IsTranslatable;
	use Capability\IsTranslatable;

	// TODO: translateFile and multiple
	public function value(): Value\Picture
	{
		if ($this->translateFile) {
			return new Value\TranslatedPicture($this->owner, $this, $this->valueContext);
		}

		return new Value\Picture($this->owner, $this, $this->valueContext);
	}

	public function properties(): array
	{
		$value = $this->value();
		$count = $value->count();

		// Generate thumbs
		// TODO: add it to the api data. Currently we assume in the frontend that they are existing
		for ($i = 0; $i < $count; $i++) {
			$value->width(400)->url(false, $i);
		}

		return parent::properties();
	}

	public function structure(mixed $value = null): array
	{
		return $this->getFileStructure('picture', $value);
	}

	public function shape(): Shape
	{
		$limitValidators = $this->limitValidators();
		$shape = Shapes::create()->title($this->label)->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:picture');

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
