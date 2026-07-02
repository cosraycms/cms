<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Extra;
use Celemas\Sire\Shape;
use Cosray\Schema\TranslateMode;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value;

class File extends Field implements Capability\Limitable, Capability\Translatable
{
	use Capability\IsLimitable;
	use Capability\IsTranslatable;

	public function control(): Control
	{
		return Control::file();
	}

	/** @return list<TranslateMode> */
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

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
		$this->addType($shape);
		$fileShape = $this->fileListShape();

		if ($this->isAsymmetricallyTranslated()) {
			$i18nShape = Shapes::create();
			$locales = $this->owner->locales();
			$defaultLocale = $locales->getDefault()->id;

			foreach ($locales as $locale) {
				$localeValidators = $limitValidators;
				$localeField = $i18nShape
					->add($locale->id, $fileShape)
					->prepare(Prepare::nullAsEmpty(...));

				if ($this->isRequired() && $locale->id === $defaultLocale) {
					$localeValidators[] = 'required';
				} else {
					$localeField->optional()->nullable();
				}

				$localeField->rules(...$localeValidators);
			}

			$value = $shape
				->add('value', $i18nShape)
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		} else {
			$value = $shape
				->add('value', $this->zxxShape($fileShape, $limitValidators))
				->rules(...$this->validators)
				->prepare(Prepare::nullAsEmpty(...));
		}

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}

	protected function fileListShape(): Shape
	{
		$fileShape = Shapes::list();
		$fileShape->add('file', 'string')->rules('required');
		$fileShape->add('meta', Shapes::create()->extra(Extra::Allow))->optional()->nullable();

		return $fileShape;
	}
}
