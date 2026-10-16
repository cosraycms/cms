<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Schema\TranslateMode;
use Cosray\Value;
use Override;

class Video extends File
{
	#[Override]
	public function control(): Control
	{
		return Control::video();
	}

	/** @return list<TranslateMode> */
	#[Override]
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric, TranslateMode::Asymmetric];
	}

	#[Override]
	public function value(): Value\Video
	{
		return new Value\Video($this->owner, $this, $this->valueContext);
	}

	#[Override]
	public function structure(mixed $value = null): array
	{
		if ($this->isAsymmetricallyTranslated()) {
			return $this->getTranslatableFileStructure('video', $value);
		}

		return $this->getFileStructure('video', $value);
	}
}
