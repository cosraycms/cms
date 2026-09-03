<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Review;
use Celema\Sire\Shape;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Types;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value\ValueContext;

/**
 * Shared by fields whose value is a list of typed rows (Entries, Blocks).
 * A row type is a class whose Field properties declare the row's schema;
 * its fields are built, ordered, validated and serialized the same way
 * for both fields.
 */
trait RowTypes
{
	abstract public function allows(string $type): bool;

	/** The word for one row in messages: 'entry' or 'block'. */
	abstract protected function rowKind(): string;

	/** @param class-string $type */
	abstract protected function assertRowField(string $type, Definition $definition): void;

	/** Applied to every row field after `init()`. */
	protected function configureRowField(Field $field, Definition $definition): void
	{
		unset($field, $definition);
	}

	/**
	 * @param class-string $type
	 * @param array<string, mixed> $data
	 * @return array<string, Field>
	 */
	protected function rowFieldsFor(string $type, array $data = []): array
	{
		if (!$this->allows($type)) {
			throw new RuntimeException(
				"{$this->fieldKind()} field '{$this->name}' does not allow {$this->rowKind()} type '{$type}'",
			);
		}

		return $this->orderedRowFields($type, $this->buildRowFields($type, $data));
	}

	/**
	 * @param class-string $type
	 * @param array<string, mixed> $data
	 * @return array<string, Field>
	 */
	protected function buildRowFields(string $type, array $data): array
	{
		$fields = [];

		foreach (Definitions::for($type)->fields() as $definition) {
			$this->assertRowField($type, $definition);
			$name = $definition->name;
			$fieldData = $data[$name] ?? [];

			if (!is_array($fieldData)) {
				$fieldData = [];
			}

			$fieldClass = $definition->type;
			$field = new $fieldClass($name, $this->owner, new ValueContext($name, $fieldData));
			$field->init($this->services(), $definition->property);
			$this->configureRowField($field, $definition);
			$fields[$name] = $field;
		}

		return $fields;
	}

	/**
	 * @param class-string $type
	 * @param array<string, Field> $fields
	 * @return array<string, Field>
	 */
	protected function orderedRowFields(string $type, array $fields): array
	{
		$order = $this->nodeTypes()->get($type, 'fieldOrder');

		if (!is_array($order)) {
			return $fields;
		}

		// The node schema validated the order against the type's fields.
		$ordered = [];

		foreach ($order as $name) {
			$ordered[$name] = $fields[$name];
		}

		return [...$ordered, ...array_diff_key($fields, $ordered)];
	}

	/**
	 * The per-type field table carried in the control descriptor: the
	 * editor views render rows and templates from it, and the form patch
	 * casts submitted rows against it.
	 *
	 * @param class-string $type
	 * @return array{type: class-string, label: string, fields: list<array>, fieldsets: list<array>}
	 */
	protected function rowTypeProperties(string $type): array
	{
		$fields = $this->rowFieldsFor($type);

		return [
			'type' => $type,
			'label' => __((string) $this->nodeTypes()->get($type, 'label')),
			'fields' => array_values(array_map(
				static fn(Field $field): array => $field->properties(),
				$fields,
			)),
			'fieldsets' => Fieldsets::serialize(
				Definitions::for($type)->fieldsets(),
				array_keys($fields),
				$fields,
				$type,
			),
		];
	}

	/**
	 * @param class-string $type
	 * @param array<string, mixed> $value
	 * @return array<string, array>
	 */
	protected function rowStructure(string $type, array $value): array
	{
		$structure = [];

		foreach ($this->rowFieldsFor($type) as $name => $field) {
			$fieldData = $value[$name] ?? null;
			$fieldValue = is_array($fieldData) ? $fieldData['value'] ?? null : null;
			$fieldStructure = $field->structure($fieldValue);

			if (is_array($fieldData)) {
				$structure[$name] = array_replace_recursive($fieldStructure, $fieldData);
				$structure[$name]['type'] = $fieldStructure['type'];

				continue;
			}

			$structure[$name] = $fieldStructure;
		}

		return $structure;
	}

	/**
	 * Sire runs finalize and review callbacks on rule-clean data only, so
	 * rows arrive here with an allowed `type` and array `fields`.
	 *
	 * @param array<string, mixed> $values
	 */
	protected function finalizeRowFields(mixed $value, array $values): mixed
	{
		$result = $this->rowShape((string) $values['type'])->validate(is_array($value) ? $value : []);

		return $result->valid() ? $result->values() : $value;
	}

	protected function reviewRowFields(Review $review): void
	{
		foreach ($review->values() as $index => $row) {
			$result = $this->rowShape((string) $row['type'])->validate(is_array($row['fields']) ? $row['fields'] : []);

			if ($result->valid()) {
				continue;
			}

			foreach ($result->issues() as $issue) {
				$review->addError(
					[$index, 'fields', ...$issue->path],
					$issue->message,
					$issue->code,
					$issue->params,
				);
			}
		}
	}

	/** @param class-string $type */
	protected function rowShape(string $type): Shape
	{
		$shape = Shapes::create();

		foreach ($this->rowFieldsFor($type) as $name => $field) {
			$shape
				->add($name, $field->shape())
				->optional()
				->nullable()
				->prepare(Prepare::nullAsEmpty(...));
		}

		return $shape;
	}

	protected function nodeTypes(): Types
	{
		return $this->services()->types;
	}

	private function fieldKind(): string
	{
		return basename(str_replace('\\', '/', self::class));
	}
}
