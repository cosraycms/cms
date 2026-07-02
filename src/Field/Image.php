<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Schema\TranslateMode;
use Cosray\Value;

class Image extends File
{
	public function control(): Control
	{
		return Control::image();
	}

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
}
