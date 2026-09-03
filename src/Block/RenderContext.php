<?php

declare(strict_types=1);

namespace Cosray\Block;

use Cosray\Assets\Asset;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Field;
use Cosray\Field\Owner;

/**
 * What a block type needs to render itself on the frontend site, plus
 * the render arguments (`tag`, `prefix`, `class`, and whatever the
 * types read, like `imageSizes`) validated once per render.
 */
final class RenderContext
{
	private const string NAME = '/^[a-z][a-z0-9-]*$/i';

	private readonly string $tag;
	private readonly string $prefix;
	private readonly string $class;

	public function __construct(
		public readonly Owner $owner,
		public readonly string $fieldName,
		public readonly int $columns,
		public readonly array $args,
	) {
		$this->tag = $this->name('tag', 'div');
		$this->prefix = $this->name('prefix', 'cms');
		$class = $args['class'] ?? '';
		$this->class = is_string($class) ? trim($class) : '';
	}

	/** The container element's tag. */
	public function tag(): string
	{
		return $this->tag;
	}

	/** Every generated class name starts with this. */
	public function prefix(): string
	{
		return $this->prefix;
	}

	/** The extra container class from the `class` argument, unescaped. */
	public function class(): string
	{
		return $this->class;
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

	/** The catalog asset a media item references, if it exists. */
	public function asset(string $uid): ?Asset
	{
		return $uid === '' ? null : $this->owner->assets()->get($uid);
	}

	private function name(string $key, string $default): string
	{
		$value = $this->args[$key] ?? $default;

		if (!is_string($value) || !preg_match(self::NAME, $value)) {
			throw new RuntimeException(
				"Blocks error: `{$key}` must be a plain name (letters, digits and dashes, starting with a letter)",
			);
		}

		return $value;
	}

	private function filled(mixed $value): bool
	{
		return $value !== null && $value !== '' && $value !== [];
	}
}
