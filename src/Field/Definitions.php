<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Contract\Embedded;
use Cosray\Exception\RuntimeException;
use Cosray\Schema\Description;
use Cosray\Schema\Fieldset;
use Cosray\Schema\Label;
use Cosray\Schema\Width;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

final class Definitions
{
	/** @var array<class-string, self> */
	private static array $cache = [];

	/**
	 * @param class-string $class
	 * @param array<string, Definition> $fields
	 * @param array<string, EmbeddedDefinition> $embedded
	 * @param list<FieldsetDefinition> $fieldsets
	 */
	private function __construct(
		public readonly string $class,
		private readonly array $fields,
		private readonly array $embedded,
		private readonly array $fieldsets,
	) {}

	/** @param class-string $class */
	public static function for(string $class): self
	{
		return self::$cache[$class] ??= self::discover($class);
	}

	public static function clear(): void
	{
		self::$cache = [];
	}

	/** @return array<string, Definition> */
	public function fields(): array
	{
		return $this->fields;
	}

	public function field(string $name): ?Definition
	{
		return $this->fields[$name] ?? null;
	}

	/** @return list<string> */
	public function names(): array
	{
		return array_keys($this->fields);
	}

	/** @return array<string, EmbeddedDefinition> */
	public function embedded(): array
	{
		return $this->embedded;
	}

	public function embed(string $name): ?EmbeddedDefinition
	{
		return $this->embedded[$name] ?? null;
	}

	/** @return list<FieldsetDefinition> */
	public function fieldsets(): array
	{
		return $this->fieldsets;
	}

	/** @param class-string $class */
	private static function discover(string $class): self
	{
		$reflection = new ReflectionClass($class);
		$fields = [];
		$embedded = [];
		$fieldsets = [];

		foreach ($reflection->getProperties() as $property) {
			$type = self::namedType($property);

			if ($type !== null && self::isField($type)) {
				self::rejectFieldset($class, $property);
				self::addField(
					$fields,
					new Definition($property->getName(), $type, $property),
					$class,
				);
				continue;
			}

			if ($type !== null && self::isEmbedded($type)) {
				$definition = self::discoverEmbedded($class, $property, $type);
				$embedded[$definition->name] = $definition;

				foreach ($definition->fields as $field) {
					self::addField($fields, $field, $class);
				}

				if ($definition->fieldset !== null) {
					$fieldsets[] = $definition->fieldset;
				}

				continue;
			}

			if (self::containsEmbedded($property->getType())) {
				throw new RuntimeException(
					"Embedded property '{$class}::\${$property->getName()}' requires one non-nullable named type.",
				);
			}

			self::rejectFieldset($class, $property);
		}

		foreach ($embedded as $name => $definition) {
			if (isset($fields[$name])) {
				throw new RuntimeException(
					"Embedded property '{$class}::\${$name}' collides with the flat field '{$name}'.",
				);
			}
		}

		return new self($class, $fields, $embedded, $fieldsets);
	}

	/**
	 * @param class-string $owner
	 * @param class-string<Embedded> $type
	 */
	private static function discoverEmbedded(
		string $owner,
		ReflectionProperty $property,
		string $type,
	): EmbeddedDefinition {
		$name = $property->getName();
		$where = "{$owner}::\${$name}";
		$reflection = new ReflectionClass($type);

		if ($property->isStatic()) {
			throw new RuntimeException("Embedded property '{$where}' must not be static.");
		}

		if ($property->isReadOnly()) {
			throw new RuntimeException("Embedded property '{$where}' must not be readonly.");
		}

		if ($property->isPromoted() || $property->hasDefaultValue()) {
			throw new RuntimeException(
				"Embedded property '{$where}' must be uninitialized and must not be constructor-promoted.",
			);
		}

		$typeReflection = $property->getType();

		if (!$typeReflection instanceof ReflectionNamedType || $typeReflection->allowsNull()) {
			throw new RuntimeException(
				"Embedded property '{$where}' requires one non-nullable named type.",
			);
		}

		if (!$reflection->isInstantiable()) {
			throw new RuntimeException("Embedded type '{$type}' on '{$where}' must be instantiable.");
		}

		$fields = [];

		foreach ($reflection->getProperties() as $child) {
			$childType = self::namedType($child);

			if ($childType !== null && self::isEmbedded($childType)) {
				throw new RuntimeException(
					"Embedded property '{$where}' contains unsupported nested embed '{$type}::\${$child->getName()}'.",
				);
			}

			if (self::containsEmbedded($child->getType())) {
				throw new RuntimeException(
					"Embedded property '{$where}' contains unsupported nested embed '{$type}::\${$child->getName()}'.",
				);
			}

			if ($childType === null || !self::isField($childType)) {
				self::rejectFieldset($type, $child);
				continue;
			}

			self::rejectFieldset($type, $child);
			self::addField(
				$fields,
				new Definition($child->getName(), $childType, $child, $name),
				$type,
			);
		}

		if ($fields === []) {
			throw new RuntimeException("Embedded type '{$type}' on '{$where}' does not declare any fields.");
		}

		$fieldset = self::fieldset($property, $reflection, array_keys($fields));

		return new EmbeddedDefinition($name, $type, $property, $fields, $fieldset);
	}

	/**
	 * @param ReflectionClass<Embedded> $class
	 * @param list<string> $fields
	 */
	private static function fieldset(
		ReflectionProperty $property,
		ReflectionClass $class,
		array $fields,
	): ?FieldsetDefinition {
		$attributes = $property->getAttributes(Fieldset::class);

		if ($attributes === []) {
			foreach ([Label::class, Description::class, Width::class] as $attribute) {
				if ($property->getAttributes($attribute) !== []) {
					throw new RuntimeException(
						"Layout attribute '{$attribute}' on inline embed "
							. "'{$property
								->getDeclaringClass()
								->getName()}::\${$property->getName()}' requires #[Fieldset].",
					);
				}
			}

			return null;
		}

		$label = null;
		$labels = $property->getAttributes(Label::class);

		if ($labels !== []) {
			$label = $labels[0]->newInstance()->label;
		} else {
			$labels = $class->getAttributes(Label::class);

			if ($labels !== []) {
				$label = $labels[0]->newInstance()->label;
			}
		}

		$description = null;
		$descriptions = $property->getAttributes(Description::class);

		if ($descriptions !== []) {
			$description = $descriptions[0]->newInstance()->description;
		}

		$width = 100;
		$widths = $property->getAttributes(Width::class);

		if ($widths !== []) {
			$width = $widths[0]->newInstance()->width;
		}

		if ($width < 1 || $width > 100) {
			throw new RuntimeException(
				"Fieldset width on '{$property
					->getDeclaringClass()
					->getName()}::\${$property->getName()}' must be between 1 and 100.",
			);
		}

		return new FieldsetDefinition($property->getName(), $label, $description, $width, $fields);
	}

	/**
	 * @param array<string, Definition> $fields
	 * @param class-string $owner
	 */
	private static function addField(array &$fields, Definition $field, string $owner): void
	{
		if (isset($fields[$field->name])) {
			throw new RuntimeException(
				"Field '{$field->name}' is declared more than once in the flat schema for '{$owner}'.",
			);
		}

		$fields[$field->name] = $field;
	}

	/** @return class-string|null */
	private static function namedType(ReflectionProperty $property): ?string
	{
		$type = $property->getType();

		if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
			return null;
		}

		return $type->getName();
	}

	/** @param class-string $type */
	private static function isField(string $type): bool
	{
		return is_subclass_of($type, Field::class);
	}

	/** @param class-string $type */
	private static function isEmbedded(string $type): bool
	{
		return is_a($type, Embedded::class, true);
	}

	private static function containsEmbedded(?ReflectionType $type): bool
	{
		if ($type === null) {
			return false;
		}

		if ($type instanceof ReflectionNamedType) {
			return !$type->isBuiltin() && self::isEmbedded($type->getName());
		}

		foreach ($type->getTypes() as $part) {
			if (
				$part instanceof ReflectionNamedType
					&& !$part->isBuiltin()
					&& self::isEmbedded($part->getName())
			) {
				return true;
			}
		}

		return false;
	}

	/** @param class-string $owner */
	private static function rejectFieldset(string $owner, ReflectionProperty $property): void
	{
		if ($property->getAttributes(Fieldset::class) === []) {
			return;
		}

		throw new RuntimeException(
			"The #[Fieldset] attribute on '{$owner}::\${$property->getName()}' requires an Embedded-typed property.",
		);
	}
}
