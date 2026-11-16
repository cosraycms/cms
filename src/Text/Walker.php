<?php

declare(strict_types=1);

namespace Cosray\Text;

use Cosray\Block\Registry;
use Cosray\Field;
use Cosray\Field\Condition;
use Cosray\Field\Definition;
use Cosray\Field\Definitions;
use Cosray\Richtext\Envelope;
use Cosray\Schema\Allows;
use Cosray\Schema\When;

/**
 * Walks a node's stored content for its texts, each with a JSON pointer to
 * where it is stored: text and textarea values, richtext paragraphs and
 * headings, also inside block and entry rows. Only fields the node type
 * declares and whose conditions hold count, as only those are rendered.
 *
 * @internal
 *
 * @phpstan-type Found array{field: string, locale: string, pointer: string, text: string, id: string}
 */
final class Walker
{
	/** Text fields that hold code or markup rather than prose. */
	private const array MARKUP = [Field\Code::class, Field\Iframe::class];

	/** Richtext containers whose children can hold texts; code blocks hold none. */
	private const array CONTAINERS = ['doc', 'bulletList', 'orderedList', 'listItem', 'blockquote'];

	public function __construct(
		private readonly Registry $blocks,
	) {}

	/**
	 * @param class-string $class
	 * @param array<mixed> $content
	 * @return list<Found>
	 */
	public function texts(string $uid, string $class, array $content): array
	{
		$found = [];
		$this->fields($class, $content, [], null, null, $found);
		$texts = [];
		$seen = [];

		foreach ($found as [$field, $locale, $segments, $text]) {
			if (trim($text) === '') {
				continue;
			}

			// Equal texts in one field and locale stay apart by their order.
			$key = "{$field}\0{$locale}\0{$text}";
			$seen[$key] = ($seen[$key] ?? 0) + 1;

			$texts[] = [
				'field' => $field,
				'locale' => $locale,
				'pointer' => Pointer::to($segments),
				'text' => $text,
				'id' => Text::id($uid, $field, $locale, $text, $seen[$key]),
			];
		}

		return $texts;
	}

	/**
	 * @param class-string $class
	 * @param array<mixed> $content
	 * @param list<string|int> $path
	 * @param list<array{string, string, list<string|int>, string}> $found
	 */
	private function fields(
		string $class,
		array $content,
		array $path,
		?string $field,
		?string $locale,
		array &$found,
	): void {
		foreach (Definitions::for($class)->fields() as $name => $definition) {
			if (!$this->active($definition, $content)) {
				continue;
			}

			$data = $content[$name] ?? null;
			$map = is_array($data) && is_array($data['value'] ?? null) ? $data['value'] : [];

			foreach ($map as $key => $value) {
				$key = (string) $key;
				$at = [...$path, $name, 'value', $key];
				$scope = $key === Field\Field::NEUTRAL_LOCALE ? $locale : $key;
				$top = $field ?? $name;
				$types = $this->rowTypes($definition);

				if ($types !== null) {
					foreach (is_array($value) ? $value : [] as $index => $row) {
						$type = is_array($row) ? $row['type'] ?? null : null;

						if (!is_string($type) || !in_array($type, $types, true) || !class_exists($type)) {
							continue;
						}

						$fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
						$this->fields($type, $fields, [...$at, $index, 'fields'], $top, $scope, $found);
					}

					continue;
				}

				$unitLocale = $scope ?? Field\Field::NEUTRAL_LOCALE;

				if (is_a($definition->type, Field\RichText::class, true)) {
					if (is_array($data) && Envelope::isStructured($data) && is_array($value)) {
						$this->richtext($value, $at, $top, $unitLocale, $found);
					}

					continue;
				}

				if (is_string($value) && self::prose($definition->type)) {
					$found[] = [$top, $unitLocale, $at, $value];
				}
			}
		}
	}

	/**
	 * @param array<mixed> $node
	 * @param list<string|int> $at
	 * @param list<array{string, string, list<string|int>, string}> $found
	 */
	private function richtext(array $node, array $at, string $field, string $locale, array &$found): void
	{
		$text = Unit::text($node);

		if ($text !== null) {
			$found[] = [$field, $locale, $at, $text];

			return;
		}

		if (!in_array($node['type'] ?? null, self::CONTAINERS, true)) {
			return;
		}

		foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $index => $child) {
			if (is_array($child)) {
				$this->richtext($child, [...$at, 'content', $index], $field, $locale, $found);
			}
		}
	}

	/**
	 * The row types a blocks or entries field renders, null for other fields.
	 *
	 * @return list<string>|null
	 */
	private function rowTypes(Definition $definition): ?array
	{
		$blocks = is_a($definition->type, Field\Blocks::class, true);

		if (!$blocks && !is_a($definition->type, Field\Entries::class, true)) {
			return null;
		}

		$allows = $definition->property->getAttributes(Allows::class);

		if ($allows !== []) {
			return array_values($allows[0]->newInstance()->types);
		}

		return $blocks ? array_values($this->blocks->all()) : [];
	}

	private static function prose(string $type): bool
	{
		if (!is_a($type, Field\Text::class, true)) {
			return false;
		}

		return array_all(self::MARKUP, static fn(string $markup): bool => !is_a($type, $markup, true));
	}

	/** @param array<mixed> $content */
	private function active(Definition $definition, array $content): bool
	{
		foreach ($definition->property->getAttributes(When::class) as $attribute) {
			if (!Condition::active($attribute->newInstance()->condition(), $content)) {
				return false;
			}
		}

		return true;
	}
}
