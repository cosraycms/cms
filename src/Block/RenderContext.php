<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Assets\Asset;
use Cosray\Field\Field;
use Cosray\Field\Owner;
use Cosray\Value\Block;
use Cosray\Value\Value;
use Cosray\Value\ValueContext;

/**
 * What a block type needs to render itself on the frontend site.
 */
final class RenderContext
{
	public function __construct(
		public readonly Owner $owner,
		public readonly string $fieldName,
		public readonly int $columns,
		public readonly array $args,
	) {}

	public function prefix(): string
	{
		return (string) ($this->args['prefix'] ?? 'cms');
	}

	/**
	 * The block's effective scalar value for the current locale.
	 */
	public function value(Block $block): string
	{
		$value = $block->data['value'] ?? [];

		if (!is_array($value)) {
			return is_string($value) || is_numeric($value) ? (string) $value : '';
		}

		$value = $this->effective($value);

		return is_string($value) || is_numeric($value) ? (string) $value : '';
	}

	/**
	 * Resolve a locale map along the locale fallback chain.
	 */
	public function effective(array $map): mixed
	{
		$locale = $this->owner->locale();

		while ($locale) {
			if ($this->filled($map[$locale->id] ?? null)) {
				return $map[$locale->id];
			}

			$locale = $locale->fallback();
		}

		if ($this->filled($map[Field::NEUTRAL_LOCALE] ?? null)) {
			return $map[Field::NEUTRAL_LOCALE];
		}

		return null;
	}

	/**
	 * Block data reshaped so media field classes can consume it.
	 */
	public function media(Block $block): array
	{
		$data = $block->data;
		$data['value'] = [Field::NEUTRAL_LOCALE => $data['value'] ?? $data['files'] ?? []];

		return $data;
	}

	/** @param class-string<Field> $class */
	public function valueObject(string $class, array $data): Value
	{
		return new $class(
			$this->fieldName,
			$this->owner,
			new ValueContext($this->fieldName, $data),
		)->value();
	}

	/** The catalog asset a media item references, if it exists. */
	public function asset(string $uid): ?Asset
	{
		return $uid === '' ? null : $this->owner->assets()->get($uid);
	}

	private function filled(mixed $value): bool
	{
		return $value !== null && $value !== '' && $value !== [];
	}
}
