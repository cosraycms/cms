<?php

declare(strict_types=1);

namespace Cosray\Field;

use Cosray\Schema\When;
use Cosray\Value\ValueContext;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;

class FieldHydrator
{
	public function __construct(
		private readonly Services $services,
	) {}

	/**
	 * Scan $target for Field-typed properties, instantiate each Field with
	 * the given FieldOwner and content data, then set them on the target.
	 *
	 * @return string[] Discovered field names
	 */
	public function hydrate(object $target, array $content, Owner $owner): array
	{
		$fieldNames = [];
		$rc = new ReflectionClass($target);

		foreach ($rc->getProperties() as $property) {
			$name = $property->getName();

			if (!$property->hasType()) {
				continue;
			}

			$type = $property->getType();

			if ($type::class === ReflectionUnionType::class) {
				continue;
			}

			$typeName = $type->getName();

			if (is_subclass_of($typeName, Field::class)) {
				if ($property->isInitialized($target)) {
					continue;
				}

				$property->setValue($target, $this->initField($property, $typeName, $content, $owner));
				$fieldNames[] = $name;
			}
		}

		return $fieldNames;
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
