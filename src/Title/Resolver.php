<?php

declare(strict_types=1);

namespace Cosray\Title;

use Cosray\Contract\Title as TitleContract;
use Cosray\Field\Definitions;
use Cosray\Field\Field;
use Cosray\Field\Text;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\Factory;
use Cosray\Node\Types;

/** Resolves runtime and materialized titles from one shared descriptor. */
class Resolver
{
	public const string KIND_DYNAMIC = 'dynamic';
	public const string KIND_FIELD = 'field';
	public const string KIND_NONE = 'none';

	public function __construct(
		private readonly Types $types,
	) {}

	/**
	 * @param class-string $class
	 * @return array{kind: string, field?: string, embedded?: string}
	 */
	public function descriptor(string $class): array
	{
		if (is_a($class, TitleContract::class, true)) {
			return ['kind' => self::KIND_DYNAMIC];
		}

		$titleEmbedded = $this->types->get($class, 'titleEmbedded');

		if (is_string($titleEmbedded) && $titleEmbedded !== '') {
			return ['kind' => self::KIND_DYNAMIC, 'embedded' => $titleEmbedded];
		}

		$titleField = $this->types->get($class, 'titleField');

		if (is_string($titleField) && $titleField !== '' && $this->isTextField($class, $titleField)) {
			return ['kind' => self::KIND_FIELD, 'field' => $titleField];
		}

		if ($this->isTextField($class, 'title')) {
			return ['kind' => self::KIND_FIELD, 'field' => 'title'];
		}

		return ['kind' => self::KIND_NONE];
	}

	/**
	 * The writable Text field behind the title: the descriptor's field for
	 * a field-kind title, or the conventional `title` field for a dynamic
	 * one — the common `implements Title` idiom computes over exactly that
	 * field. Null when neither exists.
	 *
	 * @param class-string $class
	 */
	public function writableField(string $class): ?string
	{
		$descriptor = $this->descriptor($class);

		if ($descriptor['kind'] === self::KIND_FIELD) {
			return $descriptor['field'];
		}

		return $this->isTextField($class, 'title') ? 'title' : null;
	}

	public function resolve(object $node): string
	{
		$descriptor = $this->descriptor($node::class);

		if ($descriptor['kind'] === self::KIND_FIELD) {
			$field = Factory::fieldFor($node, $descriptor['field']);

			return $field instanceof Text ? $field->value()->unwrap() ?? '' : '';
		}

		return $this->provider($node, $descriptor)?->title() ?? '';
	}

	/**
	 * Pick the title for a locale out of a materialized title map, walking the
	 * locale fallback chain and then the neutral key. Mirrors how a translated
	 * field value resolves in {@see \Cosray\Value\Value::effective()}.
	 *
	 * Null when the map holds nothing usable for that chain, which is the
	 * caller's signal to fall back to live resolution.
	 *
	 * @param array<string, mixed> $map
	 */
	public function stored(array $map, ?Locale $locale): ?string
	{
		while ($locale) {
			$title = $this->text($map, $locale->id);

			if ($title !== null) {
				return $title;
			}

			$locale = $locale->fallback();
		}

		return $this->text($map, Field::NEUTRAL_LOCALE);
	}

	/**
	 * @param null|array{kind: string, field?: string, embedded?: string} $descriptor
	 */
	public function provider(object $node, ?array $descriptor = null): ?TitleContract
	{
		$descriptor ??= $this->descriptor($node::class);

		if ($descriptor['kind'] !== self::KIND_DYNAMIC) {
			return null;
		}

		if (isset($descriptor['embedded'])) {
			$embedded = Factory::embeddedFor($node, $descriptor['embedded']);

			return $embedded instanceof TitleContract ? $embedded : null;
		}

		return $node instanceof TitleContract ? $node : null;
	}

	/**
	 * Extract the localized title map from stored content for a field-based title.
	 *
	 * @return array<string, string>
	 */
	public function fieldMap(array $content, string $field): array
	{
		$value = $content[$field]['value'] ?? null;

		if (!is_array($value)) {
			return [];
		}

		$map = [];

		foreach ($value as $locale => $text) {
			if (!is_string($locale) || !is_string($text)) {
				continue;
			}

			$text = trim($text);

			if ($text !== '') {
				$map[$locale] = $text;
			}
		}

		return $map;
	}

	/**
	 * @param callable(Locale): string $titleFor
	 * @return array<string, string>
	 */
	public function dynamicMap(callable $titleFor, Locales $locales): array
	{
		$map = [];
		$count = 0;

		foreach ($locales as $locale) {
			$count++;
			$text = trim($titleFor($locale));

			if ($text !== '') {
				$map[$locale->id] = $text;
			}
		}

		if (count($map) === $count && count(array_unique($map)) === 1) {
			return [Field::NEUTRAL_LOCALE => (string) reset($map)];
		}

		return $map;
	}

	/** @param array<string, mixed> $map */
	private function text(array $map, string $key): ?string
	{
		$title = $map[$key] ?? null;

		return is_string($title) && trim($title) !== '' ? $title : null;
	}

	/** @param class-string $class */
	private function isTextField(string $class, string $property): bool
	{
		$definition = Definitions::for($class)->field($property);

		return $definition !== null && is_a($definition->type, Text::class, true);
	}
}
