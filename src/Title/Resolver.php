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

	/** @param class-string $class */
	private function isTextField(string $class, string $property): bool
	{
		$definition = Definitions::for($class)->field($property);

		return $definition !== null && is_a($definition->type, Text::class, true);
	}
}
