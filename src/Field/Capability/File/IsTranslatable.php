<?php

declare(strict_types=1);

namespace Cosray\Field\Capability\File;

use Cosray\Schema\TranslateMode;

trait IsTranslatable
{
	public function translateFile(bool $translate = true): static
	{
		if ($translate) {
			return $this->translate(TranslateMode::Asymmetric);
		}

		$this->translateMode = null;

		return $this;
	}

	public function isFileTranslatable(): bool
	{
		return $this->isAsymmetricallyTranslated();
	}

	public function getTranslateFile(): bool
	{
		return $this->isFileTranslatable();
	}
}
