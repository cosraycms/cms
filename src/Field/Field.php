<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celemas\Sire\Extra;
use Celemas\Sire\Shape;
use Cosray\Field\Schema\Handler;
use Cosray\Field\Schema\Registry;
use Cosray\Validation\Shapes;
use Cosray\Value\Value;
use Cosray\Value\ValueContext;
use ReflectionProperty;

abstract class Field implements
	Capability\Defaultable,
	Capability\Describable,
	Capability\Hidable,
	Capability\Immutable,
	Capability\Labelable,
	Capability\Requirable,
	Capability\Resizable,
	Capability\Validatable
{
	use Capability\IsRequirable;
	use Capability\IsLabelable;
	use Capability\IsDescribable;
	use Capability\IsHidable;
	use Capability\IsImmutable;
	use Capability\IsDefaultable;
	use Capability\IsResizable;
	use Capability\IsValidatable;

	public const string NEUTRAL_LOCALE = 'zxx';

	public readonly string $type;

	/** @var list<array{object, Handler}> */
	protected array $meta = [];

	protected ?Registry $schemaRegistry = null;

	final public function __construct(
		public readonly string $name,
		public readonly Owner $owner,
		protected readonly ValueContext $valueContext,
	) {
		$this->type = $this::class;
	}

	public function __toString(): string
	{
		return $this->value()->__toString();
	}

	abstract public function value(): Value;

	abstract public function structure(mixed $value = null): array;

	abstract public function shape(): Shape;

	public function isset(): bool
	{
		return $this->value()->isset();
	}

	public function initSchema(ReflectionProperty $property, Registry $registry): void
	{
		$this->schemaRegistry = $registry;

		foreach ($property->getAttributes() as $attr) {
			$instance = $attr->newInstance();
			$handler = $registry->getHandler($instance);

			if ($handler === null) {
				continue;
			}

			$handler->apply($instance, $this);
			$this->meta[] = [$instance, $handler];
		}
	}

	public function schemaRegistry(): Registry
	{
		return $this->schemaRegistry ??= Registry::withDefaults();
	}

	public function properties(): array
	{
		$properties = [
			'name' => $this->name,
			'type' => $this::class,
		];

		foreach ($this->meta as [$meta, $handler]) {
			$properties = array_merge($properties, $handler->properties($meta, $this));
		}

		return $properties;
	}

	public function getFileStructure(string $type, mixed $value = null): array
	{
		unset($type);

		return [
			'type' => $this::class,
			'value' => $this->listValueMap($value),
		];
	}

	public function getSimpleStructure(string $type, mixed $value = null): array
	{
		unset($type);

		return [
			'type' => $this::class,
			'value' => $this->scalarValueMap($value),
		];
	}

	protected function addType(Shape $shape): void
	{
		$shape->add('type', 'string')->rules('required', 'in:' . $this::class);
	}

	protected function addMeta(Shape $shape): void
	{
		$shape->add('meta', $this->metaShape())->optional()->nullable();
	}

	protected function metaShape(): Shape
	{
		return Shapes::create()->extra(Extra::Allow);
	}

	protected function zxxShape(string|Shape $valueShape, array $validators = []): Shape
	{
		$shape = Shapes::create();
		$field = $shape->add(self::NEUTRAL_LOCALE, $valueShape)->rules(...$validators);

		if (!$this->isRequired()) {
			$field->optional()->nullable();
		}

		return $shape;
	}

	protected function scalarValue(mixed $value = null): mixed
	{
		return $value ?? $this->default;
	}

	protected function scalarValueMap(mixed $value = null): array
	{
		$value ??= $this->default;

		if (is_array($value) && array_key_exists(self::NEUTRAL_LOCALE, $value)) {
			return $value;
		}

		return [self::NEUTRAL_LOCALE => $value];
	}

	protected function listValueMap(mixed $value = null): array
	{
		$value ??= $this->default ?? [];

		if (is_array($value) && array_key_exists(self::NEUTRAL_LOCALE, $value)) {
			return $value;
		}

		return [self::NEUTRAL_LOCALE => is_array($value) ? $value : []];
	}
}
