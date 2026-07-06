<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Field;
use Iterator;

/**
 * The stored value of a Reference field: an ordered, language-neutral
 * list of target node uids. Resolution to node objects is a consumer
 * concern (e.g. `$cms->node($uid)`); the value itself exposes the uids.
 *
 * @implements Iterator<int, string>
 */
class Reference extends Value implements Iterator
{
	private int $pointer = 0;

	/** @var list<string>|null */
	private ?array $cache = null;

	public function __toString(): string
	{
		return $this->uid() ?? '';
	}

	/** @return list<string> */
	public function uids(): array
	{
		if ($this->cache !== null) {
			return $this->cache;
		}

		$value = $this->data['value'] ?? [];
		$items = is_array($value) ? $value[Field::NEUTRAL_LOCALE] ?? [] : [];
		$uids = [];

		foreach (is_array($items) ? $items : [] as $item) {
			$uid = is_array($item) ? $item['uid'] ?? null : null;

			if (is_string($uid) && $uid !== '') {
				$uids[] = $uid;
			}
		}

		return $this->cache = $uids;
	}

	public function uid(): ?string
	{
		return $this->uids()[0] ?? null;
	}

	public function count(): int
	{
		return count($this->uids());
	}

	public function unwrap(): array
	{
		return $this->uids();
	}

	public function json(): mixed
	{
		return $this->data;
	}

	public function isset(): bool
	{
		return $this->uids() !== [];
	}

	public function rewind(): void
	{
		$this->pointer = 0;
	}

	public function current(): string
	{
		return $this->uids()[$this->pointer];
	}

	public function key(): int
	{
		return $this->pointer;
	}

	public function next(): void
	{
		$this->pointer++;
	}

	public function valid(): bool
	{
		return isset($this->uids()[$this->pointer]);
	}
}
