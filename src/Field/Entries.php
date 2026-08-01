<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Review;
use Celema\Sire\Shape;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Types;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value\Entries as EntriesValue;
use Cosray\Value\ValueContext;

class Entries extends Field implements Capability\Limitable
{
	use Capability\IsLimitable;

	public function control(): Control
	{
		return Control::entries();
	}

	/** @var list<class-string> */
	protected array $allowedEntryTypes = [];

	public function value(): EntriesValue
	{
		$this->requireAllowedEntryTypes();

		return new EntriesValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$this->requireAllowedEntryTypes();
		$value ??= $this->valueContext->data['value'][self::NEUTRAL_LOCALE] ?? $this->default ?? [];

		if (!is_array($value)) {
			$value = [];
		}

		$structures = [];

		foreach ($value as $entryData) {
			if (!is_array($entryData)) {
				continue;
			}

			$type = $entryData['type'] ?? null;

			if (!is_string($type) || !$this->allows($type)) {
				continue;
			}

			$entryValue = $entryData['fields'] ?? [];

			if (!is_array($entryValue)) {
				$entryValue = [];
			}

			$structures[] = [
				'uid' => is_string($entryData['uid'] ?? null) ? $entryData['uid'] : null,
				'type' => $type,
				'fields' => $this->entryStructure($type, $entryValue),
			];
		}

		return [
			'type' => $this::class,
			'value' => [self::NEUTRAL_LOCALE => $structures],
		];
	}

	public function shape(): Shape
	{
		$this->requireAllowedEntryTypes();

		$shape = Shapes::create();
		$this->addType($shape);

		$itemShape = Shapes::list();
		$itemShape
			->add('uid', 'string')
			->rules('required');
		$itemShape
			->add('type', 'string')
			->rules('required', 'in:' . implode(',', $this->allowedEntryTypes));
		$itemShape
			->add('fields', Shapes::create())
			->rules('required')
			->finalize($this->finalizeEntryValue(...));
		$itemShape->review($this->reviewEntryValues(...));

		$value = $shape
			->add('value', $this->zxxShape($itemShape, $this->limitValidators()))
			->rules(...$this->validators)
			->prepare(Prepare::nullAsEmpty(...));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		$this->addMeta($shape);

		return $shape;
	}

	/** @param class-string ...$types */
	public function allow(string ...$types): static
	{
		if ($types === []) {
			throw new RuntimeException('Entries fields require at least one allowed entry type');
		}

		foreach ($types as $type) {
			if (!class_exists($type)) {
				throw new RuntimeException("Entries field '{$this->name}' allows unknown entry type '{$type}'");
			}

			if ($type === self::class || is_subclass_of($type, self::class)) {
				throw new RuntimeException(
					"Entries field '{$this->name}' entry type '{$type}' must not extend Entries",
				);
			}
		}

		$this->allowedEntryTypes = array_values(array_unique([
			...$this->allowedEntryTypes,
			...$types,
		]));

		return $this;
	}

	/** @return list<class-string> */
	public function allowedEntryTypes(): array
	{
		$this->requireAllowedEntryTypes();

		return $this->allowedEntryTypes;
	}

	/** @return array<string, Field> */
	public function entryFields(?string $type = null): array
	{
		$this->requireAllowedEntryTypes();
		$type ??= $this->allowedEntryTypes[0];

		return $this->entryFieldsFor($type);
	}

	/**
	 * @param class-string $type
	 * @param array<string, mixed> $data
	 * @return array<string, Field>
	 */
	public function entryFieldsFor(string $type, array $data = []): array
	{
		if (!$this->allows($type)) {
			throw new RuntimeException("Entries field '{$this->name}' does not allow entry type '{$type}'");
		}

		return $this->orderedFields($type, $this->buildEntryFields($type, $data));
	}

	public function properties(): array
	{
		$this->requireAllowedEntryTypes();

		$result = parent::properties();
		$result['type'] = Entries::class;
		$result['entryTypes'] = [];

		foreach ($this->allowedEntryTypes as $type) {
			$fields = $this->entryFieldsFor($type);
			$result['entryTypes'][] = [
				'type' => $type,
				'label' => $this->nodeTypes()->get($type, 'label'),
				'fields' => array_values(array_map(
					static fn(Field $field): array => $field->properties(),
					$fields,
				)),
				'fieldsets' => $this->entryFieldsets($type, $fields),
				// Initial content for a freshly added entry — the editor
				// clones this instead of knowing field types.
				'init' => array_map(
					static fn(Field $field): array => $field->structure(),
					$fields,
				),
			];
		}

		return $result;
	}

	public function allows(string $type): bool
	{
		return in_array($type, $this->allowedEntryTypes, true);
	}

	/**
	 * @param class-string $type
	 * @param array<string, mixed> $entryValue
	 * @return array<string, array>
	 */
	protected function entryStructure(string $type, array $entryValue): array
	{
		$structure = [];

		foreach ($this->entryFieldsFor($type) as $name => $entryField) {
			$entryFieldData = $entryValue[$name] ?? null;
			$entryFieldValue = is_array($entryFieldData) ? $entryFieldData['value'] ?? null : null;
			$entryFieldStructure = $entryField->structure($entryFieldValue);

			if (is_array($entryFieldData)) {
				$structure[$name] = array_replace_recursive($entryFieldStructure, $entryFieldData);
				$structure[$name]['type'] = $entryFieldStructure['type'];

				continue;
			}

			$structure[$name] = $entryFieldStructure;
		}

		return $structure;
	}

	/** @param array<string, mixed> $values */
	protected function finalizeEntryValue(mixed $value, array $values): mixed
	{
		$type = $values['type'] ?? null;

		if (!is_string($type) || !$this->allows($type) || !is_array($value)) {
			return $value;
		}

		$result = $this->entryShape($type)->validate($value);

		return $result->valid() ? $result->values() : $value;
	}

	protected function reviewEntryValues(Review $review): void
	{
		foreach ($review->values() as $index => $entryData) {
			if (!is_array($entryData)) {
				continue;
			}

			$type = $entryData['type'] ?? null;

			if (!is_string($type) || !$this->allows($type)) {
				continue;
			}

			$value = $entryData['fields'] ?? null;

			if (!is_array($value)) {
				continue;
			}

			$result = $this->entryShape($type)->validate($value);

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
	protected function entryShape(string $type): Shape
	{
		$shape = Shapes::create();

		foreach ($this->entryFieldsFor($type) as $name => $entryField) {
			$shape
				->add($name, $entryField->shape())
				->optional()
				->nullable()
				->prepare(Prepare::nullAsEmpty(...));
		}

		return $shape;
	}

	/**
	 * @param class-string $type
	 * @param array<string, mixed> $data
	 * @return array<string, Field>
	 */
	protected function buildEntryFields(string $type, array $data = []): array
	{
		$fields = [];

		foreach (Definitions::for($type)->fields() as $definition) {
			$name = $definition->name;
			$fieldData = $data[$name] ?? [];

			if (!is_array($fieldData)) {
				$fieldData = [];
			}

			$fieldClass = $definition->type;
			$field = new $fieldClass(
				$name,
				$this->owner,
				new ValueContext($name, $fieldData),
			);

			$field->init($this->services(), $definition->property);
			$fields[$name] = $field;
		}

		return $fields;
	}

	/**
	 * @param class-string $type
	 * @param array<string, Field> $fields
	 * @return array<string, Field>
	 */
	protected function orderedFields(string $type, array $fields): array
	{
		$order = $this->nodeTypes()->get($type, 'fieldOrder');

		if (!is_array($order)) {
			return $fields;
		}

		$ordered = [];

		foreach ($order as $name) {
			if (!is_string($name) || !isset($fields[$name])) {
				continue;
			}

			$ordered[$name] = $fields[$name];
		}

		return [...$ordered, ...array_diff_key($fields, $ordered)];
	}

	/**
	 * @param class-string $type
	 * @param array<string, Field> $fields
	 * @return list<array{name: string, label: ?string, description: ?string, width: int, fields: list<string>}>
	 */
	protected function entryFieldsets(string $type, array $fields): array
	{
		$result = [];
		$ordered = array_keys($fields);

		foreach (Definitions::for($type)->fieldsets() as $fieldset) {
			$members = array_values(array_filter(
				$ordered,
				static fn(string $name): bool => in_array($name, $fieldset->fields, true),
			));

			if ($members === []) {
				continue;
			}

			$positions = array_map(
				static fn(string $name): int => (int) array_search($name, $ordered, true),
				$members,
			);

			if ($positions !== range($positions[0], $positions[0] + count($positions) - 1)) {
				throw new RuntimeException(
					"Field order for entry type '{$type}' splits fieldset '{$fieldset->name}'.",
				);
			}

			$visible = array_values(array_filter(
				$members,
				static fn(string $name): bool => !($fields[$name]->properties()['hidden'] ?? false),
			));

			if ($visible === []) {
				continue;
			}

			$result[] = [
				'name' => $fieldset->name,
				'label' => $fieldset->label === null ? null : __($fieldset->label),
				'description' => $fieldset->description === null ? null : __($fieldset->description),
				'width' => $fieldset->width,
				'fields' => $visible,
			];
		}

		return $result;
	}

	protected function requireAllowedEntryTypes(): void
	{
		if ($this->allowedEntryTypes === []) {
			throw new RuntimeException("Entries field '{$this->name}' requires #[Allows(...)]");
		}
	}

	protected function nodeTypes(): Types
	{
		return $this->services()->types;
	}
}
