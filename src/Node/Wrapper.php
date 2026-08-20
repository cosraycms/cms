<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Cms;
use Cosray\Context;
use Cosray\Exception\RuntimeException;
use Cosray\Finder\Nodes;
use Cosray\Locale;
use Cosray\Title\Resolver as TitleResolver;
use ReflectionClass;

class Wrapper
{
	public readonly Meta $meta;

	public function __construct(
		private readonly object $node,
		private readonly array $fieldNames,
		private readonly Types $types,
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

		if (!$locale && $this->context) {
			$locale = $this->context->locale();
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
	 * Return the inner node if the given object is a Wrapper,
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
		return new TitleResolver($this->types)->resolve(self::unwrap($this->node));
	}

	/**
	 * The node's title for listings, pickers and other labelling, read from the
	 * materialized title column instead of resolving it again per node.
	 *
	 * Falls back to title() when the stored map has nothing for the active
	 * locale chain: a node saved before the column existed, or a locale added
	 * since the last `db:titles` rebuild.
	 *
	 * A dynamic title composed from *other* nodes can be stale here — the map
	 * is rewritten when the node itself is saved, not when its sources change.
	 * Use title() where that matters; rendering still resolves live.
	 */
	public function label(?Locale $locale = null): string
	{
		$map = Factory::dataFor($this->node)['title'] ?? null;

		if (!is_array($map)) {
			return $this->title();
		}

		if (!$locale && $this->context) {
			$locale = $this->context->locale();
		}

		return new TitleResolver($this->types)->stored($map, $locale) ?? $this->title();
	}

	public function children(string $query = ''): Nodes
	{
		if ($this->context === null || $this->cms === null || $this->nodeFactory === null) {
			// Finders and the view renderer pass the cms and context, so every
			// wrapper reaching a template or a node can do this. Only the
			// title-resolution proxy in Serializer is built without them.
			throw new RuntimeException('This node proxy was built without cms access');
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

	public function __get(string $name): mixed
	{
		if (in_array($name, $this->fieldNames, true)) {
			$value = Factory::fieldFor($this->node, $name)->value();

			return $value->isset() ? $value : null;
		}

		$embedded = Factory::embeddedFor($this->node, $name);

		if ($embedded !== null) {
			return $embedded;
		}

		if (!property_exists($this->node, $name)) {
			return null;
		}

		$property = new ReflectionClass($this->node)->getProperty($name);

		return $property->isPublic() && $property->isInitialized($this->node)
			? $property->getValue($this->node)
			: null;
	}

	public function __isset(string $name): bool
	{
		if (in_array($name, $this->fieldNames, true)) {
			return Factory::fieldFor($this->node, $name)->value()->isset();
		}

		if (Factory::embeddedFor($this->node, $name) !== null) {
			return true;
		}

		if (!property_exists($this->node, $name)) {
			return false;
		}

		$property = new ReflectionClass($this->node)->getProperty($name);

		return (
			$property->isPublic()
				&& $property->isInitialized($this->node)
				&& $property->getValue($this->node) !== null
		);
	}

	public function __call(string $name, array $args): mixed
	{
		return $this->node->{$name}(...$args);
	}
}
