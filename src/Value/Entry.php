<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * @property-read Field\Entries $field
 */
class Entry extends Value
{
	protected array $fields = [];

	public function __construct(
		Field\Owner $owner,
		Field\Entries $field,
		ValueContext $context,
	) {
		parent::__construct($owner, $field, $context);

		$this->initFields();
	}

	public function __toString(): string
	{
		return $this->render();
	}

	public function json(): array
	{
		return $this->unwrap();
	}

	public function unwrap(): array
	{
		$result = [];

		foreach ($this->fields as $name => $field) {
			$result[$name] = $field->structure();
		}

		return $result;
	}

	public function isset(): bool
	{
		return count($this->fields) > 0;
	}

	public function render(mixed ...$args): string
	{
		$out = '<div class="entry">';

		foreach ($this->fields as $field) {
			$out .= $field->value()->render(...$args);
		}

		$out .= '</div>';

		return $out;
	}

	public function __get(string $name): mixed
	{
		if (isset($this->fields[$name])) {
			return $this->fields[$name]->value();
		}

		throw new \Cosray\Exception\NoSuchProperty("Entry doesn't have field '{$name}'");
	}

	protected function initFields(): void
	{
		$entriesClass = $this->field::class;
		$reflection = new ReflectionClass($entriesClass);

		foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
			$type = $property->getType();

			if (!$type || !$type instanceof ReflectionNamedType) {
				continue;
			}

			$fieldClass = $type->getName();

			if (!is_subclass_of($fieldClass, Field\Field::class)) {
				continue;
			}

			$fieldData = $this->data[$property->getName()] ?? null;
			$fieldContext = new ValueContext($property->getName(), $fieldData);

			$field = new $fieldClass(
				$property->getName(),
				$this->owner,
				$fieldContext,
			);

			$field->initSchema($property, $this->field->schemaRegistry());
			$this->fields[$property->getName()] = $field;
		}
	}
}
