<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

use Cosray\Block\Registry;
use Cosray\Contract\Block;
use Cosray\Contract\Title;
use Cosray\Exception\RuntimeException;
use Cosray\Field;
use Cosray\Field\Condition;
use Cosray\Field\Definitions;
use Cosray\Field\Schema\FulltextHandler;
use Cosray\Locale;
use Cosray\Node\Types;
use Cosray\Richtext\Envelope;
use Cosray\Richtext\Spec;
use Cosray\Schema\Allows;
use Cosray\Schema\Fulltext;
use Cosray\Schema\FulltextWeight;
use Cosray\Schema\Translate;
use Cosray\Schema\TranslateMode;
use Cosray\Schema\When;
use Cosray\Title\Resolver;
use ReflectionClass;
use ReflectionMethod;

final class Builder
{
	private readonly Resolver $titles;
	private array $schemas = [];

	public function __construct(
		Types $types,
		private readonly Registry $blocks,
	) {
		$this->titles = new Resolver($types);
	}

	/** @param class-string $class */
	public function build(string $class, array $content, array $titles, Locale $locale): Document
	{
		$parts = [];
		$weight = $this->titleWeight($class);
		$title = $weight instanceof FulltextWeight ? $this->titles->stored($titles, $locale) : null;

		if ($title !== null) {
			$this->append($parts, 'title()', $weight, $title);
		}

		$this->fields($class, $content, $locale, null, '', $parts);

		return new Document($parts, $weight instanceof FulltextWeight && $title === null);
	}

	/** @param class-string $class */
	private function titleWeight(string $class): FulltextWeight|false|null
	{
		$descriptor = $this->titles->descriptor($class);

		if ($descriptor['kind'] !== Resolver::KIND_DYNAMIC) {
			return null;
		}

		$provider = isset($descriptor['embedded'])
			? Definitions::for($class)->embed($descriptor['embedded'])->type
			: $class;
		$attributes = new ReflectionMethod($provider, 'title')->getAttributes(Fulltext::class);

		return $attributes === [] ? null : $attributes[0]->newInstance()->fulltextWeight;
	}

	private function schema(string $class): array
	{
		if (isset($this->schemas[$class])) {
			return $this->schemas[$class];
		}

		$definitions = Definitions::for($class);
		foreach ([$class, ...array_column($definitions->embedded(), 'type')] as $owner) {
			$reflection = new ReflectionClass($owner);
			foreach ($reflection->getMethods() as $method) {
				if ($method->getAttributes(Fulltext::class) === []) {
					continue;
				}
				if ($method->getName() !== 'title' || !is_a($owner, Title::class, true)) {
					throw new RuntimeException(
						"Fulltext on '{$owner}::{$method->getName()}()' requires the Title contract's title() implementation.",
					);
				}
			}
			foreach ($reflection->getProperties() as $property) {
				if (
					$property->getAttributes(Fulltext::class) !== []
					&& $definitions->field($property->getName()) === null
				) {
					throw new RuntimeException(
						"Fulltext on '{$owner}::\${$property->getName()}' requires a supported field.",
					);
				}
			}
		}

		$schema = [];
		foreach ($definitions->fields() as $name => $field) {
			$attributes = $field->property->getAttributes(Fulltext::class);
			$weight = $attributes === [] ? null : $attributes[0]->newInstance()->fulltextWeight;
			if ($weight === false) {
				continue;
			}
			if (!FulltextHandler::supports($field->type)) {
				if ($weight !== null) {
					throw new RuntimeException(
						"Fulltext does not support '{$class}::\${$name}' of type '{$field->type}'.",
					);
				}
				continue;
			}
			$entry = [
				'type' => $field->type,
				'weight' => $weight,
				'conditions' => array_map(
					static fn($attribute): array => $attribute->newInstance()->condition(),
					$field->property->getAttributes(When::class),
				),
			];
			if (in_array($field->type, [Field\Blocks::class, Field\Entries::class], true)) {
				$allows = $field->property->getAttributes(Allows::class);
				$entry['types'] = $field->type === Field\Blocks::class ? $this->blocks->all() : [];
				if ($allows !== []) {
					$entry['types'] = $allows[0]->newInstance()->types;
				}
				if ($entry['types'] === []) {
					throw new RuntimeException("Fulltext field '{$class}::\${$name}' requires allowed row types.");
				}
				foreach ($entry['types'] as $type) {
					if (
						!class_exists($type)
						|| $field->type === Field\Blocks::class
						&& !is_a($type, Block::class, true)
					) {
						throw new RuntimeException(
							"Invalid row type '{$type}' in fulltext field '{$class}::\${$name}'.",
						);
					}
					foreach (Definitions::for($type)->fields() as $child) {
						if (
							is_a($child->type, Field\Blocks::class, true)
							|| is_a($child->type, Field\Entries::class, true)
						) {
							throw new RuntimeException(
								"Fulltext field '{$class}::\${$name}' cannot contain nested repeaters in '{$type}'.",
							);
						}
					}
				}
				$translation = $field->property->getAttributes(Translate::class);
				$entry['asymmetric'] =
					$field->type === Field\Blocks::class
					&& $translation !== []
					&& $translation[0]->newInstance()->mode === TranslateMode::Asymmetric;
			}
			$schema[$name] = $entry;
		}

		return $this->schemas[$class] = $schema;
	}

	private function fields(
		string $class,
		array $content,
		Locale $locale,
		?FulltextWeight $inherited,
		string $path,
		array &$parts,
	): void {
		foreach ($this->schema($class) as $name => $field) {
			foreach ($field['conditions'] as $condition) {
				if (!Condition::active($condition, $content)) {
					continue 2;
				}
			}
			$weight = $field['weight'] ?? $inherited;
			$data = $content[$name] ?? [];
			$map = is_array($data) && is_array($data['value'] ?? null) ? $data['value'] : [];
			$where = $path . $name;
			if (isset($field['types'])) {
				$rows = $field['asymmetric']
					? $this->effective($map, $locale)
					: $map[Field\Field::NEUTRAL_LOCALE] ?? [];
				foreach (is_array($rows) ? $rows : [] as $index => $row) {
					if (!is_array($row) || !in_array($row['type'] ?? null, $field['types'], true)) {
						continue;
					}
					$this->fields(
						$row['type'],
						is_array($row['fields'] ?? null) ? $row['fields'] : [],
						$locale,
						$weight,
						"{$where}[{$index}].",
						$parts,
					);
				}
				continue;
			}
			if ($weight === null) {
				continue;
			}
			$value = $this->effective($map, $locale);
			if ($value === null) {
				continue;
			}
			if ($field['type'] === Field\RichText::class) {
				if (!Envelope::isStructured($data)) {
					continue;
				}
				$value = $this->richtext($value);
			}
			if (!is_string($value)) {
				throw new RuntimeException(
					"Fulltext field '{$where}' must contain text, not " . get_debug_type($value) . '.',
				);
			}
			$this->append($parts, $where, $weight, $value);
		}
	}

	private function effective(array $map, Locale $locale): mixed
	{
		foreach ([$locale->id, ...$locale->fallbacks(), Field\Field::NEUTRAL_LOCALE] as $id) {
			$value = $map[$id] ?? null;
			if ($value !== null && $value !== '' && $value !== []) {
				return $value;
			}
		}
		return null;
	}

	// Reader-tolerant like Richtext\Renderer: unknown types are skipped with their subtree.
	private function richtext(mixed $node): string
	{
		$type = is_array($node) ? $node['type'] ?? null : null;
		if (!is_string($type) || !Spec::isNode($type)) {
			return '';
		}
		if ($type === 'text') {
			return is_string($node['text'] ?? null) ? $node['text'] : '';
		}
		$boundary = $type === 'hardBreak' || !Spec::isInline($type) ? "\n" : '';
		if (Spec::isLeaf($type)) {
			return $boundary;
		}
		$text = '';
		foreach (is_array($node['content'] ?? null) ? $node['content'] : [] as $child) {
			$text .= $this->richtext($child);
		}
		return $text . $boundary;
	}

	private function append(array &$parts, string $field, FulltextWeight $weight, string $text): void
	{
		// Headline markers must never occur in original source text.
		$text = trim(str_replace(["\0", "\x01", "\x02"], '', $text));
		if ($text !== '') {
			$parts[] = ['field' => $field, 'weight' => $weight->name, 'text' => $text];
		}
	}
}
