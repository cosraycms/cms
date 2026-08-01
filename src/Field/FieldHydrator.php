<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Assets\Repository;
use Cosray\Contract\HasInit;
use Cosray\Exception\RuntimeException;
use Cosray\Schema\When;
use Cosray\Value\ValueContext;
use ReflectionClass;
use ReflectionProperty;

class FieldHydrator
{
	public function __construct(
		private readonly Services $services,
	) {}

	/**
	 * Hydrate direct fields for callers that do not provide embedded object creation.
	 *
	 * @return list<string>
	 */
	public function hydrate(object $target, array $content, Owner $owner): array
	{
		return array_keys($this->hydrateAll($target, $content, $owner)->fields);
	}

	/**
	 * @param callable(EmbeddedDefinition): object $createEmbedded
	 */
	public function hydrateEmbedded(
		object $target,
		array $content,
		Owner $owner,
		callable $createEmbedded,
	): Hydration {
		return $this->hydrateAll($target, $content, $owner, $createEmbedded);
	}

	/**
	 * @param null|callable(EmbeddedDefinition): object $createEmbedded
	 */
	private function hydrateAll(
		object $target,
		array $content,
		Owner $owner,
		?callable $createEmbedded = null,
	): Hydration {
		$uids = Repository::collectUids($content);

		if ($uids !== []) {
			$owner->assets()->preload($uids);
		}

		$targetClass = $target::class;
		$definitions = Definitions::for($targetClass);
		$embedded = [];

		foreach ($definitions->embedded() as $definition) {
			if ($createEmbedded === null) {
				throw new RuntimeException(
					"Hydrating '{$targetClass}' requires an embedded object factory.",
				);
			}

			$instance = $createEmbedded($definition);
			$type = $definition->type;

			if (!$instance instanceof $type) {
				$instanceClass = $instance::class;
				throw new RuntimeException(
					"Embedded factory returned '{$instanceClass}' for '{$definition->type}'.",
				);
			}

			$definition->property->setValue($target, $instance);
			$embedded[$definition->name] = $instance;
		}

		$fields = [];

		foreach ($definitions->fields() as $definition) {
			$fieldTarget = $definition->embedded === null ? $target : $embedded[$definition->embedded];

			if ($definition->property->isInitialized($fieldTarget)) {
				continue;
			}

			$field = $this->initField($definition->property, $definition->type, $content, $owner);
			$definition->property->setValue($fieldTarget, $field);
			$fields[$definition->name] = $field;
		}

		foreach ($embedded as $instance) {
			if (!$instance instanceof HasInit) {
				continue;
			}

			$instance->init();
		}

		return new Hydration($fields, $embedded);
	}

	public static function getField(object $target, string $name): Field
	{
		$rc = new ReflectionClass($target);

		return $rc->getProperty($name)->getValue($target);
	}

	/**
	 * @return Field[]
	 */
	public static function getFields(object $target, array $fieldNames): array
	{
		$rc = new ReflectionClass($target);
		$fields = [];

		foreach ($fieldNames as $name) {
			$fields[$name] = $rc->getProperty($name)->getValue($target);
		}

		return $fields;
	}

	public function services(): Services
	{
		return $this->services;
	}

	protected function initField(
		ReflectionProperty $property,
		string $fieldType,
		array $content,
		Owner $owner,
	): Field {
		$fieldName = $property->getName();
		$data = $content[$fieldName] ?? [];

		// A field whose When condition is not met hydrates with empty
		// data: it presents as empty to every consumer (read-time
		// enforcement) while the stored value survives untouched and
		// stays reachable through Field::raw().
		$active = $this->isActive($property, $content);
		$field = new $fieldType($fieldName, $owner, new ValueContext($fieldName, $active ? $data : []));

		$field->init($this->services, $property, $data);

		return $field;
	}

	private function isActive(ReflectionProperty $property, array $content): bool
	{
		foreach ($property->getAttributes(When::class) as $attr) {
			if (!Condition::active($attr->newInstance()->condition(), $content)) {
				return false;
			}
		}

		return true;
	}
}
