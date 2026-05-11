<?php

declare(strict_types=1);

namespace Celemas\Cms\Node;

use Celemas\Cms\Cms;
use Celemas\Cms\Context;
use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\FieldHydrator;
use Celemas\Cms\Field\Text;
use Celemas\Cms\Finder\Nodes;
use Celemas\Cms\Locale;
use Celemas\Cms\Node\Contract\Title;
use Celemas\Cms\Value\Value;
use Celemas\Core\Request;

class Node
{
	public readonly Meta $meta;

	public function __construct(
		private readonly object $node,
		private readonly array $fieldNames,
		private readonly FieldHydrator $hydrator,
		private readonly Types $types,
		private readonly ?Request $request = null,
		private readonly ?Context $context = null,
		private readonly ?Cms $cms = null,
		private readonly ?Factory $nodeFactory = null,
	) {
		$this->meta = new Meta($this->node, $this->types);
	}

	/**
	 * Resolve the locale-aware URL path for this node.
	 *
	 * Uses the paths stored in Factory's WeakMap data,
	 * walking the locale fallback chain until a path is found.
	 */
	public function path(?Locale $locale = null): string
	{
		$data = Factory::dataFor($this->node);
		$paths = $data['paths'] ?? [];

		if (!$locale && $this->request) {
			$locale = $this->request->get('locale');
		}

		while ($locale) {
			if (isset($paths[$locale->id])) {
				return $paths[$locale->id];
			}

			$locale = $locale->fallback();
		}

		throw new RuntimeException('No url path found');
	}

	/**
	 * Return the inner node if the given object is a Node wrapper,
	 * otherwise return the object unchanged.
	 */
	public static function unwrap(object $object): object
	{
		return $object instanceof self ? $object->node : $object;
	}

	public function meta(string $key, mixed $default = null): mixed
	{
		return $this->meta->get($key, $default);
	}

	public function title(): string
	{
		$inner = self::unwrap($this->node);

		if ($inner instanceof Title) {
			return $inner->title();
		}

		$titleField = $this->types->get($inner::class, 'titleField');

		if (is_string($titleField) && $titleField !== '') {
			$field = $this->hydrator->getField($inner, $titleField);

			if (!$field instanceof Text) {
				return '';
			}

			return $field->value()->unwrap() ?? '';
		}

		if (in_array('title', $this->fieldNames, true)) {
			$field = $this->hydrator->getField($inner, 'title');

			if (!$field instanceof Text) {
				return '';
			}

			return $field->value()->unwrap() ?? '';
		}

		return '';
	}

	public function children(string $query = ''): Nodes
	{
		if ($this->context === null || $this->cms === null || $this->nodeFactory === null) {
			throw new RuntimeException('children() is only available on finder-backed node proxies');
		}

		$children = new Nodes($this->context, $this->cms, $this->nodeFactory, $this->types)
			->published(null)
			->hidden(null)
			->childrenOf($this->meta->uid);

		if (trim($query) !== '') {
			$children->filter($query);
		}

		return $children;
	}

	public function __get(string $name): ?Value
	{
		if (in_array($name, $this->fieldNames, true)) {
			$field = $this->hydrator->getField($this->node, $name);
			$value = $field->value();

			if ($value->isset()) {
				return $value;
			}

			return null;
		}

		if (property_exists($this->node, $name)) {
			return $this->node->{$name};
		}

		return null;
	}

	public function __isset(string $name): bool
	{
		if (in_array($name, $this->fieldNames, true)) {
			$field = $this->hydrator->getField($this->node, $name);

			return $field->value()->isset();
		}

		return isset($this->node->{$name});
	}

	public function __call(string $name, array $args): mixed
	{
		return $this->node->{$name}(...$args);
	}
}
