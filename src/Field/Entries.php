<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Review;
use Celemas\Sire\Shape;
use Cosray\Exception\RuntimeException;
use Cosray\Node\Types;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value\Entries as EntriesValue;
use Cosray\Value\ValueContext;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

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
			$result['entryTypes'][] = [
				'type' => $type,
				'label' => $this->nodeTypes()->get($type, 'label'),
				'fields' => array_values(array_map(
					static fn(Field $field): array => $field->properties(),
					$this->entryFieldsFor($type),
				)),
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
		$reflection = new ReflectionClass($type);

		foreach ($reflection->getProperties() as $property) {
			$fieldClass = $this->fieldClass($property);

			if ($fieldClass === null) {
				continue;
			}

			$name = $property->getName();
			$fieldData = $data[$name] ?? [];

			if (!is_array($fieldData)) {
				$fieldData = [];
			}

			$field = new $fieldClass(
				$name,
				$this->owner,
				new ValueContext($name, $fieldData),
			);

			$field->init($this->services(), $property);
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

	/** @return class-string<Field>|null */
	protected function fieldClass(ReflectionProperty $property): ?string
	{
		$type = $property->getType();

		if (!$type instanceof ReflectionNamedType) {
			return null;
		}

		$fieldClass = $type->getName();

		if (!is_subclass_of($fieldClass, Field::class)) {
			return null;
		}

		return $fieldClass;
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
