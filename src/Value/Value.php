<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Assets\Assets;
use Cosray\Exception\NoSuchProperty;
use Cosray\Field\Field;
use Cosray\Field\Field as FieldBase;
use Cosray\Field\Owner;
use Cosray\Locale;

abstract class Value
{
	public readonly string $fieldType;
	protected readonly Locale $locale;
	protected readonly Locale $defaultLocale;
	protected readonly string $fieldName;
	protected readonly array $data;

	public function __construct(
		protected readonly Owner $owner,
		protected readonly Field $field,
		protected readonly ValueContext $context,
	) {
		$this->locale = $owner->locale();
		$this->defaultLocale = $owner->defaultLocale();
		$this->data = $context->data;
		$this->fieldName = $context->fieldName;
		$this->fieldType = $field->type;
	}

	public function __get(string $name): mixed
	{
		if (array_key_exists($name, $this->data)) {
			return $this->data[$name];
		}

		if (array_key_exists($name, $this->data['meta'] ?? [])) {
			return $this->meta($name);
		}

		throw new NoSuchProperty("The field '{$this->fieldName}' doesn't have the property '{$name}'");
	}

	abstract public function __toString(): string;

	abstract public function isset(): bool;

	abstract public function json(): mixed;

	abstract public function unwrap(): mixed;

	public function styleClass(): ?string
	{
		$value = $this->meta('class');

		return is_string($value) && $value !== '' ? $value : null;
	}

	public function elementId(): ?string
	{
		$value = $this->meta('id');

		return is_string($value) && $value !== '' ? $value : null;
	}

	protected function value(): mixed
	{
		return $this->effective($this->data['value'] ?? []);
	}

	protected function zxx(): mixed
	{
		$value = $this->data['value'] ?? [];

		if (!is_array($value)) {
			return null;
		}

		return $value[FieldBase::NEUTRAL_LOCALE] ?? null;
	}

	protected function meta(string $key, mixed $default = null): mixed
	{
		$meta = $this->data['meta'][$key] ?? null;

		if (!is_array($meta)) {
			return $default;
		}

		return $this->effective($meta) ?? $default;
	}

	protected function effective(array $map): mixed
	{
		$locale = $this->locale;

		while ($locale) {
			if ($this->filled($map[$locale->id] ?? null)) {
				return $map[$locale->id];
			}

			$locale = $locale->fallback();
		}

		if ($this->filled($map[FieldBase::NEUTRAL_LOCALE] ?? null)) {
			return $map[FieldBase::NEUTRAL_LOCALE];
		}

		return null;
	}

	protected function filled(mixed $value): bool
	{
		return $value !== null && $value !== '' && $value !== [];
	}

	protected function assetsPath(): string
	{
		return 'node/' . $this->owner->uid() . '/';
	}

	protected function getAssets(): Assets
	{
		static $assets = null;

		if (!$assets) {
			$assets = new Assets($this->owner->request(), $this->owner->config());
		}

		return $assets;
	}
}
