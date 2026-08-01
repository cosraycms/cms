<?php

declare(strict_types=1);

namespace Cosray\Node;

use Cosray\Contract\Title as TitleContract;
use Cosray\Exception\NoSuchProperty;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Definitions;
use Cosray\Node\Schema\Registry;
use Cosray\Schema\Title;
use ReflectionClass;

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

		$definitions = Definitions::for($this->nodeClass);
		$selections = [];

		if (array_key_exists('titleField', $resolved)) {
			$selections[] = ['kind' => 'field', 'name' => $resolved['titleField']];
		}

		foreach ($definitions->fields() as $field) {
			if ($field->property->getAttributes(Title::class) === []) {
				continue;
			}

			$selections[] = ['kind' => 'field', 'name' => $field->name];
		}

		foreach ($definitions->embedded() as $embedded) {
			if ($embedded->property->getAttributes(Title::class) === []) {
				continue;
			}

			if (!is_a($embedded->type, TitleContract::class, true)) {
				throw new RuntimeException(
					"The #[Title] attribute on embedded property '{$this->nodeClass}::{$embedded->name}' "
					. 'requires its type to implement Cosray\\Contract\\Title.',
				);
			}

			$selections[] = ['kind' => 'embedded', 'name' => $embedded->name];
		}

		$this->validatePropertyTitles($reflection, $definitions);

		if (count($selections) > 1) {
			throw new RuntimeException(
				"Node '{$this->nodeClass}' declares more than one explicit title source.",
			);
		}

		$selection = $selections[0] ?? null;

		if ($selection === null) {
			return $resolved;
		}

		if ($selection['kind'] === 'embedded') {
			$resolved['titleEmbedded'] = $selection['name'];

			return $resolved;
		}

		$handler = $this->registry->getHandler(new Title());

		if ($handler !== null) {
			$resolved = array_merge($resolved, $handler->resolve(
				new Title((string) $selection['name']),
				$this->nodeClass,
			));
		}

		return $resolved;
	}

	private function validatePropertyTitles(
		ReflectionClass $reflection,
		Definitions $definitions,
	): void {
		foreach ($reflection->getProperties() as $property) {
			if ($property->getAttributes(Title::class) === []) {
				continue;
			}

			$field = $definitions->field($property->getName());

			if ($field !== null && $field->embedded === null || $definitions->embed($property->getName())) {
				continue;
			}

			throw new RuntimeException(
				"The #[Title] attribute on property '{$this->nodeClass}::{$property->getName()}' "
				. 'requires a field-typed or Embedded-typed property.',
			);
		}
	}
}
