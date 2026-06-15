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
		unset($type);

		$value ??= $this->default;

		return [
			'type' => $this::class,
			'value' => $this->isTranslatable()
				? $this->localeValueMap($value)
				: [self::NEUTRAL_LOCALE => $value],
		];
	}

	private function getTranslatableFileStructure(string $type, mixed $value = null): array
	{
		unset($type);

		return [
			'type' => $this::class,
			'value' => $this->localeListMap($value),
		];
	}

	private function localeValueMap(mixed $value = null): array
	{
		if (is_array($value)) {
			return $value;
		}

		$result = [];

		foreach ($this->owner->locales() as $locale) {
			$result[$locale->id] = null;
		}

		if ($value !== null) {
			$result[$this->owner->defaultLocale()->id] = $value;
		}

		return $result;
	}

	private function localeListMap(mixed $value = null): array
	{
		if (is_array($value) && !$this->isList($value)) {
			return $value;
		}

		$result = [];

		foreach ($this->owner->locales() as $locale) {
			$result[$locale->id] = [];
		}

		return $result;
	}

	private function isList(array $value): bool
	{
		return array_is_list($value);
	}
}
