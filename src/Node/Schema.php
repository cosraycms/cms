<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Exception\NoSuchProperty;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Field;
use Cosray\Node\Schema\Registry;
use Cosray\Schema\Title;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;

class Schema
{
	/** @var array<string, mixed> */
	private array $properties;

	/**
	 * @param class-string $nodeClass
	 */
	public function __construct(
		private readonly string $nodeClass,
		private readonly Registry $registry,
	) {
		$resolved = $this->resolveAttributes();
		$this->properties = $this->registry->resolveDefaults($this->nodeClass, $resolved);
	}

	public function __get(string $key): mixed
	{
		if (!$this->has($key)) {
			throw new NoSuchProperty(
				"The node schema '{$this->nodeClass}' doesn't have the property '{$key}'",
			);
		}

		return $this->get($key);
	}

	public function __isset(string $key): bool
	{
		return $this->has($key) && $this->properties[$key] !== null;
	}

	/**
	 * Get a schema property by key.
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		if (array_key_exists($key, $this->properties)) {
			return $this->properties[$key];
		}

		return $default;
	}

	public function has(string $key): bool
	{
		return array_key_exists($key, $this->properties);
	}

	/**
	 * Return all schema properties as an array.
	 *
	 * @return array<string, mixed>
	 */
	public function properties(): array
	{
		return $this->properties;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function resolveAttributes(): array
	{
		$reflection = new ReflectionClass($this->nodeClass);
		$resolved = [];

		foreach ($reflection->getAttributes() as $attribute) {
			$instance = $attribute->newInstance();
			$handler = $this->registry->getHandler($instance);

			if ($handler !== null) {
				$resolved = array_merge($resolved, $handler->resolve($instance, $this->nodeClass));
			}
		}

		foreach ($reflection->getProperties() as $property) {
			foreach ($property->getAttributes(Title::class) as $attribute) {
				$instance = $attribute->newInstance();
				$handler = $this->registry->getHandler($instance);

				if ($handler === null) {
					continue;
				}

				if (!$this->isFieldProperty($property)) {
					throw new RuntimeException(
						"The #[Title] attribute on property '{$this->nodeClass}::{$property->getName()}' "
						. 'requires a field-typed property.',
					);
				}

				$resolved = array_merge($resolved, $handler->resolve(
					new Title($property->getName()),
					$this->nodeClass,
				));
			}
		}

		return $resolved;
	}

	private function isFieldProperty(ReflectionProperty $property): bool
	{
		$type = $property->getType();

		if ($type === null || $type::class === ReflectionUnionType::class) {
			return false;
		}

		return is_subclass_of($type->getName(), Field::class);
	}
}
