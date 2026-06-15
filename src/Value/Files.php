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
		return count($this->files());
	}

	public function next(): void
	{
		$this->pointer++;
	}

	public function valid(): bool
	{
		return $this->fileItem($this->pointer) !== null;
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
		return $this->files();
	}

	public function json(): mixed
	{
		return $this->unwrap();
	}

	public function isset(): bool
	{
		return $this->fileItem(0) !== null;
	}

	protected function len(): int
	{
		return count($this->files());
	}
}
