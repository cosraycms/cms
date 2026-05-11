<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Validation\Prepare;
use Celemas\Cms\Validation\Shapes;
use Celemas\Cms\Value;
use Celemas\Sire\Shape;

class File extends Field implements
	Capability\Limitable,
	Capability\File\Translatable,
	Capability\Translatable
{
	use Capability\IsLimitable;
	use Capability\IsTranslatable;
	use Capability\File\IsTranslatable;

	public function value(): Value\File|Value\Files
	{
		if ($this->allowsMultipleItems()) {
			if ($this->translateFile) {
				return new Value\TranslatedFiles($this->owner, $this, $this->valueContext);
			}

			return new Value\Files($this->owner, $this, $this->valueContext);
		}

		if ($this->translateFile) {
			return new Value\TranslatedFile($this->owner, $this, $this->valueContext);
		}

		return new Value\File($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		if ($this->translateFile) {
			return $this->getTranslatableFileStructure('file', $value);
		}

		return $this->getFileStructure('file', $value);
	}

	public function shape(): Shape
	{
		$limitValidators = $this->limitValidators();
		$shape = Shapes::create()->title($this->label)->keepUnknown();
		$shape->add('type', 'text', 'required', 'in:file');

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
			$titleShape = Shapes::create()->title($this->label)->keepUnknown();

			foreach ($locales as $locale) {
				$titleShape->add($locale->id, 'text');
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
