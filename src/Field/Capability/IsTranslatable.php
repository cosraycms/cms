<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

use Cosray\Schema\TranslateMode;

trait IsTranslatable
{
	protected ?TranslateMode $translateMode = null;

	public function translate(TranslateMode $mode = TranslateMode::Symmetric): static
	{
		$this->translateMode = $mode;

		return $this;
	}

	public function isTranslatable(): bool
	{
		return $this->translateMode !== null;
	}

	public function translateMode(): ?TranslateMode
	{
		return $this->translateMode;
	}

	/** @return list<TranslateMode> */
	protected function supportedTranslateModes(): array
	{
		return [TranslateMode::Symmetric];
	}

	public function supportsTranslateMode(TranslateMode $mode): bool
	{
		return in_array($mode, $this->supportedTranslateModes(), true);
	}

	protected function isSymmetricallyTranslated(): bool
	{
		return $this->translateMode === TranslateMode::Symmetric;
	}

	protected function isAsymmetricallyTranslated(): bool
	{
		return $this->translateMode === TranslateMode::Asymmetric;
	}

	private function getTranslatableStructure(string $type, mixed $value = null): array
	{
		$value = $value ?: $this->default;

		$result = ['type' => $type];

		if ($value) {
			$result['value'] = $value;

			return $result;
		}

		if ($this->isTranslatable()) {
			$result['value'] = [];

			foreach ($this->owner->locales() as $locale) {
				$result['value'][$locale->id] = null;
			}
		} else {
			$result['value'] = null;
		}

		return $result;
	}

	private function getTranslatableFileStructure(string $type, mixed $value = null): array
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
