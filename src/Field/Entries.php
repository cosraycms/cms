<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Shape;
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

	protected array $entryFields = [];
	protected bool $entryFieldsInitialized = false;

	public function value(): EntriesValue
	{
		$this->initEntryFields();

		return new EntriesValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$this->initEntryFields();
		$value ??= $this->valueContext->data['value'] ?? $this->default ?? [];

		if (!is_array($value)) {
			$value = [];
		}

		$structures = [];

		foreach ($value as $entryData) {
			$entryStructure = [];

			foreach ($this->entryFields as $name => $entryField) {
				$entryFieldData = $entryData[$name] ?? null;
				$entryFieldValue = is_array($entryFieldData) ? $entryFieldData['value'] ?? null : null;
				$entryFieldStructure = $entryField->structure($entryFieldValue);

				if (is_array($entryFieldData)) {
					$entryStructure[$name] = $entryFieldStructure;

					foreach ($entryFieldData as $key => $entryFieldMetaValue) {
						if ($key === 'type' || $key === 'value') {
							continue;
						}

						$entryStructure[$name][$key] = $entryFieldMetaValue;
					}

					continue;
				}

				$entryStructure[$name] = $entryFieldStructure;
			}

			$structures[] = $entryStructure;
		}

		return [
			'type' => 'entries',
			'value' => $structures,
		];
	}

	public function shape(): Shape
	{
		$this->initEntryFields();

		$shape = Shapes::create();
		$shape
			->add('type', 'string')
			->prepare(static fn(mixed $value): mixed => $value === 'matrix' ? 'entries' : $value)
			->rules('required', 'in:entries');

		$entryShape = $this->allowsMultipleItems() ? Shapes::list() : Shapes::create();

		foreach ($this->entryFields as $name => $entryField) {
			$entryShape
				->add($name, $entryField->shape())
				->optional()
				->nullable()
				->prepare(Prepare::nullAsEmpty(...));
		}

		$value = $shape
			->add('value', $entryShape)
			->rules(...$this->validators)
			->prepare(Prepare::nullAsEmpty(...));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		return $shape;
	}

	public function entryFields(): array
	{
		$this->initEntryFields();

		return $this->entryFields;
	}

	public function properties(): array
	{
		$this->initEntryFields();

		$result = parent::properties();
		// Override type with base Entries class so the UI can find the right component
		$result['type'] = Entries::class;
		$result['entryFields'] = [];

		foreach ($this->entryFields as $entryField) {
			$result['entryFields'][] = $entryField->properties();
		}

		return $result;
	}

	protected function initEntryFields(): void
	{
		if ($this->entryFieldsInitialized) {
			return;
		}

		$this->entryFieldsInitialized = true;
		$entriesClass = $this::class;
		$reflection = new ReflectionClass($entriesClass);

		foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
			$type = $property->getType();

			if (!$type || !$type instanceof ReflectionNamedType) {
				continue;
			}

			$fieldClass = $type->getName();

			if (!is_subclass_of($fieldClass, Field::class)) {
				continue;
			}

			$entryField = new $fieldClass(
				$property->getName(),
				$this->owner,
				new ValueContext($property->getName(), []),
			);

			$entryField->initSchema($property, $this->schemaRegistry());
			$this->entryFields[$property->getName()] = $entryField;
		}
	}
}
