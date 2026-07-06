<?php

declare(strict_types=1);

namespace Cosray\Title;

use Cosray\Field\Field;
use Cosray\Field\Text;
use Cosray\Locale;
use Cosray\Locales;
use Cosray\Node\Contract\Title as TitleContract;
use Cosray\Node\Types;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Turns a node's title into the materialized locale map stored in
 * `nodes.title`. Mirrors `Node::title()`'s resolution order — a
 * `Contract\Title` node, else the schema `titleField`, else a `title`
 * text field — but produces every locale instead of the active one.
 */
class Resolver
{
	public const string KIND_DYNAMIC = 'dynamic';
	public const string KIND_FIELD = 'field';
	public const string KIND_NONE = 'none';

	public function __construct(
		private readonly Types $types,
	) {}

	/**
	 * Classify how a node type derives its title.
	 *
	 * @param class-string $class
	 *
	 * @return array{kind: string, field?: string}
	 */
	public function descriptor(string $class): array
	{
		if (is_a($class, TitleContract::class, true)) {
			return ['kind' => self::KIND_DYNAMIC];
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
	 * Extract the localized title map from stored content for a field-based
	 * title. The field value is already a `{locale: text}` map; blank
	 * entries drop out so unset locales fall back through the read path.
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
	 * Build the localized title map for a dynamic (`Contract\Title`) node by
	 * evaluating its title once per locale. When every locale yields the same
	 * string the map collapses to the neutral key, so a non-localized title
	 * stays a single entry that also covers locales added later.
	 *
	 * @param callable(Locale): string $titleFor
	 *
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

	/**
	 * @param class-string $class
	 */
	private function isTextField(string $class, string $property): bool
	{
		if (!property_exists($class, $property)) {
			return false;
		}

		$type = new ReflectionClass($class)
			->getProperty($property)
			->getType();

		if (!$type instanceof ReflectionNamedType) {
			return false;
		}

		return is_a($type->getName(), Text::class, true);
	}
}
