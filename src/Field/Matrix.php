<?php

declare(strict_types=1);

namespace Celemas\Cms\Field;

use Celemas\Cms\Validation\Prepare;
use Celemas\Cms\Validation\Shapes;
use Celemas\Cms\Value\MatrixValue;
use Celemas\Cms\Value\ValueContext;
use Celemas\Sire\Shape;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class Matrix extends Field implements Capability\Limitable
{
	use Capability\IsLimitable;

	protected array $subfields = [];
	protected bool $subfieldsInitialized = false;

	public function value(): MatrixValue
	{
		$this->initSubfields();

		return new MatrixValue($this->owner, $this, $this->valueContext);
	}

	public function structure(mixed $value = null): array
	{
		$this->initSubfields();
		$value ??= $this->valueContext->data['value'] ?? $this->default ?? [];

		if (!is_array($value)) {
			$value = [];
		}

		$structures = [];

		foreach ($value as $itemData) {
			$itemStructure = [];

			foreach ($this->subfields as $name => $subfield) {
				$subfieldData = $itemData[$name] ?? null;
				$subfieldValue = is_array($subfieldData) ? $subfieldData['value'] ?? null : null;
				$subfieldStructure = $subfield->structure($subfieldValue);

				if (is_array($subfieldData)) {
					$itemStructure[$name] = $subfieldStructure;

					foreach ($subfieldData as $key => $subfieldMetaValue) {
						if ($key === 'type' || $key === 'value') {
							continue;
						}

						$itemStructure[$name][$key] = $subfieldMetaValue;
					}

					continue;
				}

				$itemStructure[$name] = $subfieldStructure;
			}

			$structures[] = $itemStructure;
		}

		return [
			'type' => 'matrix',
			'value' => $structures,
		];
	}

	public function shape(): Shape
	{
		$shape = Shapes::create();
		$shape->add('type', 'string')->rules('required', 'in:matrix');

		$itemShape = $this->allowsMultipleItems() ? Shapes::list() : Shapes::create();

		foreach ($this->subfields as $name => $subfield) {
			$itemShape
				->add($name, $subfield->shape())
				->optional()
				->nullable()
				->prepare(Prepare::nullAsEmpty(...));
		}

		$value = $shape
			->add('value', $itemShape)
			->rules(...$this->validators)
			->prepare(Prepare::nullAsEmpty(...));

		if (!$this->isRequired()) {
			$value->optional()->nullable();
		}

		return $shape;
	}

	public function getSubfields(): array
	{
		$this->initSubfields();

		return $this->subfields;
	}

	public function properties(): array
	{
		$this->initSubfields();

		$result = parent::properties();
		// Override type with base Matrix class so the UI can find the right component
		$result['type'] = Matrix::class;
		$result['subfields'] = [];

		foreach ($this->subfields as $subfield) {
			$result['subfields'][] = $subfield->properties();
		}

		return $result;
	}

	protected function initSubfields(): void
	{
		if ($this->subfieldsInitialized) {
			return;
		}

		$this->subfieldsInitialized = true;
		$matrixClass = $this::class;
		$reflection = new ReflectionClass($matrixClass);

		foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
			$type = $property->getType();

			if (!$type || !$type instanceof ReflectionNamedType) {
				continue;
			}

			$fieldClass = $type->getName();

			if (!is_subclass_of($fieldClass, Field::class)) {
				continue;
			}

			$subfield = new $fieldClass(
				$property->getName(),
				$this->owner,
				new ValueContext($property->getName(), []),
			);

			$subfield->initSchema($property, $this->schemaRegistry());
			$this->subfields[$property->getName()] = $subfield;
		}
	}
}
