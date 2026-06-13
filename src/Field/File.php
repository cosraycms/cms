<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value;

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
			if ($this->isAsymmetricallyTranslated()) {
				return new Value\TranslatedFiles($this->owner, $this, $this->valueContext);
			}

			return new Value\Files($this->owner, $this, $this->valueContext);
		}

		if ($this->isAsymmetricallyTranslated()) {
			return new Value\TranslatedFile($this->owner, $this, $this->valueContext);
		}

		return new Value\File($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			return $this->getTranslatableFileStructure('file', $value);
		}

		return $this->getFileStructure('file', $value);
	}

	public function shape(): Shape
	{
		$limitValidators = $this->limitValidators();
		$shape = Shapes::create();
		$shape->add('type', 'string')->rules('required', 'in:file');

		if ($this->isAsymmetricallyTranslated()) {
			// File-translatable: separate file arrays per locale
			$subShape = Shapes::list();
			$subShape->add('file', 'string')->optional()->nullable();
			$subShape->add('title', 'string')->optional()->nullable();

			$i18nShape = Shapes::create();
			$locales = $this->owner->locales();

			foreach ($locales as $locale) {
				$i18nShape
					->add($locale->id, $subShape)
					->rules(...$limitValidators)
					->optional()
					->nullable()
					->prepare(Prepare::nullAsEmpty(...));
			}

			$files = $shape
				->add('files', $i18nShape)
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} elseif ($this->isSymmetricallyTranslated()) {
			// Text-translatable: shared files but translatable titles
			$fileShape = Shapes::list();
			$fileShape->add('file', 'string')->rules('required');

			$locales = $this->owner->locales();
			$titleShape = Shapes::create();

			foreach ($locales as $locale) {
				$titleShape->add($locale->id, 'string')->optional()->nullable();
			}

			$fileShape
				->add('title', $titleShape)
				->optional()
				->nullable()
				->prepare(Prepare::nullAsEmpty(...));
			$files = $shape
				->add('files', $fileShape)
				->rules(...$limitValidators, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			// Non-translatable
			$fileShape = Shapes::list();
			$fileShape->add('file', 'string')->rules('required');
			$fileShape->add('title', 'string')->optional()->nullable();
			$files = $shape
				->add('files', $fileShape)
				->rules(...$limitValidators, ...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		}

		if (!$this->isRequired()) {
			$files->optional()->nullable();
		}

		return $shape;
	}
}
