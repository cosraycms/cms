<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Schema\TranslateMode;
use Cosray\Value;

class Video extends File
{
	/** @return list<TranslateMode> */
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

	public function value(): Value\Video
	{
		return new Value\Video($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			return $this->getTranslatableFileStructure('video', $value);
		}

		return $this->getFileStructure('video', $value);
	}
}
