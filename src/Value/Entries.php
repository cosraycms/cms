<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Exception\RuntimeException;
use Cosray\Field;
use Generator;
use IteratorAggregate;

/**
 * @property-read Field\Entries $field
 */
class Entries extends Value implements IteratorAggregate
{
	/** @var list<Entry> */
	protected array $entries = [];

	public function __construct(
		Field\Owner $owner,
		Field\Entries $field,
		ValueContext $context,
	) {
		parent::__construct($owner, $field, $context);

		$this->prepareEntries();
	}

	public function __toString(): string
	{
		throw new RuntimeException(
			"The entries field '{$this->fieldName}' has no string representation. Iterate its rows in the template.",
		);
	}

	public function json(): array
	{
		return array_map(static fn(Entry $entry): array => $entry->json(), $this->entries);
	}

	public function unwrap(): array
	{
		return array_map(static fn(Entry $entry): array => $entry->unwrap(), $this->entries);
	}

	public function getIterator(): Generator
	{
		foreach ($this->entries as $entry) {
			yield $entry;
		}
	}

	public function count(): int
	{
		return count($this->entries);
	}

	public function first(): ?Entry
	{
		return $this->entries[0] ?? null;
	}

	public function last(): ?Entry
	{
		return $this->entries[count($this->entries) - 1] ?? null;
	}

	public function get(int $index): ?Entry
	{
		return $this->entries[$index] ?? null;
	}

	public function isset(): bool
	{
		return count($this->entries) > 0;
	}

	protected function prepareEntries(): void
	{
		$data = $this->data['value'][Field\Field::NEUTRAL_LOCALE] ?? [];

		if (!is_array($data)) {
			return;
		}

		foreach ($data as $entryData) {
			if (!is_array($entryData)) {
				continue;
			}

			$type = $entryData['type'] ?? null;

			if (!is_string($type) || !$this->field->allows($type)) {
				continue;
			}

			$this->entries[] = new Entry(
				$this->owner,
				$this->field,
				new ValueContext($this->fieldName, $entryData),
				$type,
			);
		}
	}
}
