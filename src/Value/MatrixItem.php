<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Field\Field;
use Celemas\Cms\Field\Matrix;
use Celemas\Cms\Field\Owner;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * @property-read Matrix $field
 */
class MatrixItem extends Value
{
	protected array $subfields = [];

	public function __construct(
		Owner $owner,
		Matrix $field,
		ValueContext $context,
	) {
		parent::__construct($owner, $field, $context);

		$this->initSubfields();
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

		foreach ($this->subfields as $name => $subfield) {
			$result[$name] = $subfield->structure();
		}

		return $result;
	}

	public function isset(): bool
	{
		return count($this->subfields) > 0;
	}

	public function render(mixed ...$args): string
	{
		$out = '<div class="matrix-item">';

		foreach ($this->subfields as $subfield) {
			$out .= $subfield->value()->render(...$args);
		}

		$out .= '</div>';

		return $out;
	}

	public function __get(string $name): mixed
	{
		if (isset($this->subfields[$name])) {
			return $this->subfields[$name]->value();
		}

		throw new \Celemas\Cms\Exception\NoSuchProperty("Matrix item doesn't have subfield '{$name}'");
	}

	protected function initSubfields(): void
	{
		$matrixClass = $this->field::class;
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

			$subfieldData = $this->data[$property->getName()] ?? null;
			$subfieldContext = new ValueContext($property->getName(), $subfieldData);

			$subfield = new $fieldClass(
				$property->getName(),
				$this->owner,
				$subfieldContext,
			);

			$subfield->initSchema($property, $this->field->schemaRegistry());
			$this->subfields[$property->getName()] = $subfield;
		}
	}
}
