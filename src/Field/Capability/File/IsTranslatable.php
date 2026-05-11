<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability\File;

trait IsTranslatable
{
	protected bool $translateFile = false;

	public function translateFile(bool $translate = true): static
	{
		$this->translateFile = $translate;
		$this->translate = $translate;

		return $this;
	}

	public function isFileTranslatable(): bool
	{
		return $this->translateFile;
	}

	public function getTranslateFile(): bool
	{
		return $this->translateFile;
	}

	protected function getTranslatableFileStructure(string $type, mixed $value = null): array
	{
		$value = $value ?: $this->default;

		$result = ['type' => $type];

		if ($value) {
			$result['files'] = $value;

			return $result;
		}

		$result['files'] = [];

		foreach ($this->owner->locales() as $locale) {
			$result['files'][$locale->id] = [];
		}

		return $result;
	}
}
