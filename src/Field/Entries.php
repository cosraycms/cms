<?php

declare(strict_types=1);

namespace Cosray\Field;

use Celema\Sire\Shape;
use Cosray\Exception\RuntimeException;
use Cosray\Validation\Prepare;
use Cosray\Validation\Shapes;
use Cosray\Value\Entries as EntriesValue;

class Entries extends Field implements Capability\Limitable
{
	use Capability\IsLimitable;
	use RowTypes;

	/** @var list<class-string> */
	protected array $allowedEntryTypes = [];

	public function control(): Control
	{
		$this->requireAllowedEntryTypes();

		$control = Control::entries()->prop('entryTypes', array_map(
			$this->rowTypeProperties(...),
			$this->allowedEntryTypes,
		));

		if ($this->limitMin > 0) {
			$control = $control->prop('min', $this->limitMin);
		}

		if ($this->limitMax >= 1) {
			$control = $control->prop('max', $this->limitMax);
		}

		return $control;
	}

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
				'fields' => $this->rowStructure($type, $entryValue),
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
			->finalize($this->finalizeRowFields(...));
		$itemShape->review($this->reviewRowFields(...));

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

		return $this->rowFieldsFor($type);
	}

	/**
	 * @param class-string $type
	 * @param array<string, mixed> $data
	 * @return array<string, Field>
	 */
	public function entryFieldsFor(string $type, array $data = []): array
	{
		return $this->rowFieldsFor($type, $data);
	}

	public function properties(): array
	{
		$result = parent::properties();
		$result['type'] = Entries::class;

		return $result;
	}

	public function allows(string $type): bool
	{
		return in_array($type, $this->allowedEntryTypes, true);
	}

	protected function rowKind(): string
	{
		return 'entry';
	}

	/**
	 * Nested typed repeaters are rejected: rows renumber against one
	 * `[data-repeater]` base, so a repeater inside a row cannot be
	 * renumbered yet.
	 */
	protected function assertRowField(string $type, Definition $definition): void
	{
		foreach ([self::class => 'entries', Blocks::class => 'blocks'] as $class => $kind) {
			if (is_a($definition->type, $class, true)) {
				throw new RuntimeException(
					"Entries field '{$this->name}' cannot contain nested {$kind} field"
						. " '{$definition->name}' in entry type '{$type}'",
				);
			}
		}
	}

	protected function requireAllowedEntryTypes(): void
	{
		if ($this->allowedEntryTypes === []) {
			throw new RuntimeException("Entries field '{$this->name}' requires #[Allows(...)]");
		}
	}
}
