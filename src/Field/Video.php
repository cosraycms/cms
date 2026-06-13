<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
use Cosray\Schema\TranslateMode;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value;

class Video extends Field implements Capability\Limitable, Capability\Translatable
{
	use Capability\IsLimitable;
	use Capability\IsTranslatable;

	/** @return list<TranslateMode> */
	public function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

	public function value(): Value\Video
	{
		if ($this->isAsymmetricallyTranslated()) {
			return new Value\Video($this->owner, $this, $this->valueContext);
		}

		return new Value\Video($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			return $this->getTranslatableFileStructure('video', $value);
		}

		return $this->getFileStructure('video', $value);
	}

	public function shape(): Shape
	{
		$limitValidators = $this->limitValidators();
		$shape = Shapes::create();
		$shape->add('type', 'string')->rules('required', 'in:video');

		if ($this->isAsymmetricallyTranslated()) {
			// Asymmetric translation: separate file arrays per locale
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
			// Symmetric translation: shared files but translatable titles
			$fileShape = Shapes::list();
			$fileShape->add('file', 'string')->rules('required');

			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;
			$titleShape = Shapes::create();

			foreach ($locales as $locale) {
				$localeValidators = [];

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				}

				$title = $titleShape->add($locale->id, 'string')->rules(...$localeValidators);

				if (!in_array('required', $localeValidators, true)) {
					$title->optional()->nullable();
				}
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
