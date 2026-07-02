<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Collection\Schema\Registry;
use Cosray\Exception\NoSuchProperty;
use ReflectionClass;

class Schema
{
	/** @var array<string, mixed> */
	private array $properties;

	/**
	 * @param class-string $class
	 */
	public function __construct(
		private readonly string $class,
		private readonly Registry $registry,
	) {
		$resolved = $this->resolveAttributes();
		$this->properties = $this->registry->resolveDefaults($this->class, $resolved);
	}

	public function __get(string $key): mixed
	{
		if (!array_key_exists($key, $this->properties)) {
			throw new NoSuchProperty(
				"The collection schema '{$this->class}' doesn't have the property '{$key}'",
			);
		}

		return $this->properties[$key];
	}

	public function __isset(string $key): bool
	{
		return array_key_exists($key, $this->properties) && $this->properties[$key] !== null;
	}

	public function get(string $key, mixed $default = null): mixed
	{
		if (array_key_exists($key, $this->properties)) {
			return $this->properties[$key];
		}

		return $default;
	}

	/** @return array<string, mixed> */
	public function properties(): array
	{
		return $this->properties;
	}

	/** @return array<string, mixed> */
	private function resolveAttributes(): array
	{
		$resolved = [];
		$reflection = new ReflectionClass($this->class);

		foreach ($reflection->getAttributes() as $attribute) {
			$instance = $attribute->newInstance();
			$handler = $this->registry->getHandler($instance);

			if ($handler === null) {
				continue;
			}

			$resolved = array_merge($resolved, $handler->resolve($instance, $this->class));
		}

		return $resolved;
	}
}
