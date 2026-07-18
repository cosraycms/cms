<?php

declare(strict_types=1);

namespace Cosray\I18n;

use Celema\Verba\Tool\Message;
use Celema\Verba\Tool\Scanner;
use Cosray\App;
use Cosray\NavigationItem;
use Cosray\Schema\Badge;
use Cosray\Schema\Description;
use Cosray\Schema\Label;
use Cosray\Schema\Options;
use ReflectionClass;

/**
 * Harvests the translatable strings Cosray reads off schemas at serialization
 * time so `i18n:sync` sees them like any `__()` call: node and collection
 * labels/badges, field labels, descriptions and option labels, and the
 * navigation section, link and collection names.
 *
 * The strings are read straight from the schema attributes by reflection and
 * from the booted navigation tree — nothing is instantiated. It mirrors the
 * runtime translation points in Node\Serializer, Field\Field and Navigation, so
 * the ids it emits are exactly the ones looked up when the panel renders.
 *
 * @api
 */
final class SchemaScanner implements Scanner
{
	/**
	 * @param list<class-string> $classes node types to reflect for schema attributes
	 * @param list<string> $labels ready display strings (navigation names)
	 */
	public function __construct(
		private readonly array $classes,
		private readonly array $labels = [],
	) {}

	/**
	 * Build a scanner from a booted app: every registered node type plus the
	 * navigation section, link and collection names.
	 */
	public static function fromApp(App $app): self
	{
		$labels = [];
		self::walk($app->navigation()->items(), $labels);

		return new self($app->bootstrap()->nodeClasses(), array_values(array_unique($labels)));
	}

	/**
	 * @return list<Message>
	 */
	public function scan(): array
	{
		/** @var array<string, Message> $messages */
		$messages = [];

		foreach ($this->labels as $label) {
			$this->add($messages, $label, 'navigation');
		}

		foreach ($this->classes as $class) {
			$this->fromClass($class, $messages);
		}

		return array_values($messages);
	}

	/**
	 * @return list<string>
	 */
	public function warnings(): array
	{
		return [];
	}

	/**
	 * @param class-string $class
	 * @param array<string, Message> $messages
	 */
	private function fromClass(string $class, array &$messages): void
	{
		$reflection = new ReflectionClass($class);

		foreach ($reflection->getAttributes(Label::class) as $attribute) {
			$this->add($messages, $attribute->newInstance()->label, $class);
		}

		foreach ($reflection->getAttributes(Badge::class) as $attribute) {
			$this->add($messages, $attribute->newInstance()->badge, $class);
		}

		foreach ($reflection->getProperties() as $property) {
			$where = $class . '::$' . $property->getName();

			foreach ($property->getAttributes(Label::class) as $attribute) {
				$this->add($messages, $attribute->newInstance()->label, $where);
			}

			foreach ($property->getAttributes(Description::class) as $attribute) {
				$this->add($messages, $attribute->newInstance()->description, $where);
			}

			foreach ($property->getAttributes(Options::class) as $attribute) {
				foreach ($this->optionLabels($attribute->newInstance()->options) as $label) {
					$this->add($messages, $label, $where);
				}
			}
		}
	}

	/**
	 * Select options mirror Field::localizeOption: only array options carry a
	 * translatable `label`; a plain-string option doubles as its own value.
	 *
	 * @param array<array-key, mixed> $options
	 * @return list<string>
	 */
	private function optionLabels(array $options): array
	{
		$labels = [];

		foreach ($options as $option) {
			if (!is_array($option) || !is_string($option['label'] ?? null)) {
				continue;
			}

			$labels[] = $option['label'];
		}

		return $labels;
	}

	/**
	 * @param array<string, Message> $messages
	 */
	private function add(array &$messages, string $id, string $location): void
	{
		if ($id === '' || isset($messages[$id])) {
			return;
		}

		$messages[$id] = new Message(null, $id, null, [$location]);
	}

	/**
	 * @param list<NavigationItem> $items
	 * @param list<string> $labels
	 */
	private static function walk(array $items, array &$labels): void
	{
		foreach ($items as $item) {
			$labels[] = $item->meta->label;
			self::walk($item->children(), $labels);
		}
	}
}
