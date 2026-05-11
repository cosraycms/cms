<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Field\Matrix;
use Celemas\Cms\Field\Owner;
use Generator;
use IteratorAggregate;

/**
 * @property-read Matrix $field
 */
class MatrixValue extends Value implements IteratorAggregate
{
	protected array $items = [];

	public function __construct(
		Owner $owner,
		Matrix $field,
		ValueContext $context,
	) {
		parent::__construct($owner, $field, $context);

		$this->prepareItems();
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

		foreach ($this->items as $item) {
			$result[] = $item->unwrap();
		}

		return $result;
	}

	public function getIterator(): Generator
	{
		foreach ($this->items as $item) {
			yield $item;
		}
	}

	public function count(): int
	{
		return count($this->items);
	}

	public function first(): ?MatrixItem
	{
		return $this->items[0] ?? null;
	}

	public function last(): ?MatrixItem
	{
		return $this->items[count($this->items) - 1] ?? null;
	}

	public function get(int $index): ?MatrixItem
	{
		return $this->items[$index] ?? null;
	}

	public function isset(): bool
	{
		return count($this->items) > 0;
	}

	public function render(mixed ...$args): string
	{
		$out = '';

		foreach ($this->items as $item) {
			$out .= $item->render(...$args);
		}

		return $out;
	}

	protected function prepareItems(): void
	{
		$data = $this->data['value'] ?? [];

		if (!is_array($data)) {
			return;
		}

		foreach ($data as $itemData) {
			if (!is_array($itemData)) {
				continue;
			}

			$this->items[] = new MatrixItem(
				$this->owner,
				$this->field,
				new ValueContext($this->fieldName, $itemData),
			);
		}
	}
}
