<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Schema\TranslateMode;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value;

class Image extends Field implements Capability\Translatable, Capability\Limitable
{
	use Capability\IsLimitable;
	use Capability\IsTranslatable;

	/** @return list<TranslateMode> */
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

	public function value(): Value\Images|Value\Image
	{
		if ($this->allowsMultipleItems()) {
			if ($this->isAsymmetricallyTranslated()) {
				return new Value\TranslatedImages($this->owner, $this, $this->valueContext);
			}

			return new Value\Images($this->owner, $this, $this->valueContext);
		}

		if ($this->isAsymmetricallyTranslated()) {
			return new Value\TranslatedImage($this->owner, $this, $this->valueContext);
		}

		return new Value\Image($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			return $this->getTranslatableFileStructure('image', $value);
		}

		return $this->getFileStructure('image', $value);
	}

	public function shape(): Shape
	{
		$limitValidators = $this->limitValidators();
		$shape = Shapes::create();
		$shape->add('type', 'string')->rules('required', 'in:image');

		if ($this->isAsymmetricallyTranslated()) {
			// Asymmetric translation: separate file arrays per locale
			$subShape = Shapes::list();
			$subShape->add('file', 'string')->optional()->nullable();
			$subShape->add('title', 'string')->optional()->nullable();
			$subShape->add('alt', 'string')->optional()->nullable();

			$i18nShape = Shapes::create();
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;

			foreach ($locales as $locale) {
				$localeValidators = $limitValidators;
				$localeField = $i18nShape
					->add($locale->id, $subShape)
					->prepare(Prepare::nullAsEmpty(...));

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				} else {
					$localeField->optional()->nullable();
				}

				$localeField->rules(...$localeValidators);
			}

			$files = $shape
				->add('files', $i18nShape)
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} elseif ($this->isSymmetricallyTranslated()) {
			// Symmetric translation: shared files but translatable titles and alt text
			$fileShape = Shapes::list();
			$fileShape->add('file', 'string')->rules('required');

			$locales = $this->owner->locales();
			$titleShape = Shapes::create();
			$altShape = Shapes::create();

			foreach ($locales as $locale) {
				$titleShape->add($locale->id, 'string')->optional()->nullable();
				$altShape->add($locale->id, 'string')->optional()->nullable();
			}

			$fileShape
				->add('title', $titleShape)
				->optional()
				->nullable()
				->prepare(Prepare::nullAsEmpty(...));
			$fileShape
				->add('alt', $altShape)
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
			$fileShape->add('alt', 'string')->optional()->nullable();
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
