<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Capability\Translatable;
use Cosray\Field\Field;
use Cosray\Field\Owner;
use Iterator;

class Files extends Value implements Iterator
{
	protected int $pointer = 0;

	public function __construct(
		Owner $owner,
		Field&Translatable $field,
		ValueContext $context,
		protected int $index = 0,
	) {
		parent::__construct($owner, $field, $context);
	}

	public function __toString(): string
	{
		return 'Files: count(' . count($this->unwrap()) . ')';
	}

	public function rewind(): void
	{
		$this->pointer = 0;
	}

	public function current(): File
	{
		return $this->get($this->pointer);
	}

	public function key(): int
	{
		return $this->pointer;
	}

	public function count(): int
	{
		return count($this->data['files']);
	}

	public function next(): void
	{
		$this->pointer++;
	}

	public function valid(): bool
	{
		return isset($this->data['files'][$this->pointer]);
	}

	public function get(int $index): File
	{
		return new File($this->owner, $this->field, $this->context, $index);
	}

	public function first(): File
	{
		return new File($this->owner, $this->field, $this->context, 0);
	}

	public function unwrap(): array
	{
		$locale = $this->locale;

		while ($locale) {
			$value = $this->data[$this->locale->id] ?? null;

			if ($value) {
				return $value;
			}

			$locale = $this->locale->fallback();
		}

		return [];
	}

	public function json(): mixed
	{
		return $this->unwrap();
	}

	public function isset(): bool
	{
		return isset($this->data['files'][0]) ? true : false;
	}

	protected function len(): int
	{
		return count($this->data['files']);
	}
}
