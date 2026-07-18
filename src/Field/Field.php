<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Contract;
use Celema\Sire\Extra;
use Celema\Sire\Shape;
use Cosray\Exception\RuntimeException;
use Cosray\Field\Schema\Handler;
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

	protected ?Services $services = null;

	/** The stored data as-is, unaffected by When deactivation. */
	protected array $raw = [];

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

	public function init(
		Services $services,
		?ReflectionProperty $property = null,
		array $raw = [],
	): void {
		$this->services = $services;
		$this->raw = $raw;

		if ($property === null) {
			return;
		}

		foreach ($property->getAttributes() as $attr) {
			$instance = $attr->newInstance();
			$handler = $services->schemas->getHandler($instance);

			if ($handler === null) {
				continue;
			}

			$handler->apply($instance, $this);
			$this->meta[] = [$instance, $handler];
		}
	}

	public function services(): Services
	{
		return $this->services ?? throw new RuntimeException("Field '{$this->name}' is not initialized");
	}

	/**
	 * The stored data as-is — the deliberate bypass around When
	 * deactivation for consumers that need the dormant value.
	 */
	public function raw(): array
	{
		return $this->raw;
	}

	public function control(): Control
	{
		return Control::text();
	}

	/**
	 * Describes the editor UI for the field's meta map — a group whose
	 * sub-control keys name the meta entries. Null means the field has
	 * no user-editable meta.
	 */
	public function metaControl(): ?Control
	{
		return null;
	}

	public function properties(): array
	{
		$properties = [
			'name' => $this->name,
			'type' => $this::class,
			'control' => $this->control()->resolve($this->services()->controls)->array(),
		];

		$metaControl = $this->metaControl();

		if ($metaControl !== null) {
			$properties['metaControl'] = $metaControl->resolve($this->services()->controls)->array();
		}

		foreach ($this->meta as [$meta, $handler]) {
			$properties = array_merge($properties, $handler->properties($meta, $this));
		}

		return $this->localize($properties);
	}

	/**
	 * Translate the field's display strings for the active locale at emit time.
	 * The schema (and thus the raw attribute strings) stays untouched because
	 * it is cached per class; only the serialized copy is localized.
	 *
	 * @param array<string, mixed> $properties
	 * @return array<string, mixed>
	 */
	private function localize(array $properties): array
	{
		foreach (['label', 'description'] as $key) {
			$value = $properties[$key] ?? null;

			if (is_string($value)) {
				$properties[$key] = __($value);
			}
		}

		$options = $properties['options'] ?? null;

		if (is_array($options)) {
			$properties['options'] = array_map($this->localizeOption(...), $options);
		}

		return $properties;
	}

	/**
	 * Translate a select option's label. Plain string options are left as-is
	 * (their value doubles as the label and must stay stable); labelled options
	 * `{value, label}` get their label translated.
	 */
	private function localizeOption(mixed $option): mixed
	{
		if (!is_array($option)) {
			return $option;
		}

		$label = $option['label'] ?? null;

		return is_string($label) ? [...$option, 'label' => __($label)] : $option;
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

	protected function zxxShape(string|Contract\Validator $valueShape, array $validators = []): Shape
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
