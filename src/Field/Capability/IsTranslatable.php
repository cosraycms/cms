<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

trait IsTranslatable
{
	protected bool $translate = false;

	public function translate(bool $translate = true): static
	{
		$this->translate = $translate;

		return $this;
	}

	public function isTranslatable(): bool
	{
		return $this->translate;
	}

	public function getTranslate(): bool
	{
		return $this->translate;
	}

	protected function getTranslatableStructure(string $type, mixed $value = null): array
	{
		$value = $value ?: $this->default;

		$result = ['type' => $type];

		if ($value) {
			$result['value'] = $value;

			return $result;
		}

		if ($this->translate) {
			$result['value'] = [];

			foreach ($this->owner->locales() as $locale) {
				$result['value'][$locale->id] = null;
			}
		} else {
			$result['value'] = null;
		}

		return $result;
	}
}
