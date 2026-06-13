<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Entries as EntriesField;
use Cosray\Field\Owner;
use Generator;
use IteratorAggregate;

/**
 * @property-read EntriesField $field
 */
class Entries extends Value implements IteratorAggregate
{
	protected array $entries = [];

	public function __construct(
		Owner $owner,
		EntriesField $field,
		ValueContext $context,
	) {
		parent::__construct($owner, $field, $context);

		$this->prepareEntries();
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

		foreach ($this->entries as $entry) {
			$result[] = $entry->unwrap();
		}

		return $result;
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

	public function render(mixed ...$args): string
	{
		$out = '';

		foreach ($this->entries as $entry) {
			$out .= $entry->render(...$args);
		}

		return $out;
	}

	protected function prepareEntries(): void
	{
		$data = $this->data['value'] ?? [];

		if (!is_array($data)) {
			return;
		}

		foreach ($data as $entryData) {
			if (!is_array($entryData)) {
				continue;
			}

			$this->entries[] = new Entry(
				$this->owner,
				$this->field,
				new ValueContext($this->fieldName, $entryData),
			);
		}
	}
}
