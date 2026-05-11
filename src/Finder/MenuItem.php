<?php

declare(strict_types=1);

namespace Celemas\Cms\Finder;

use Celemas\Cms\Context;
use Generator;
use Iterator;

class MenuItem implements Iterator
{
	protected readonly array $data;
	protected array $children;

	public function __construct(
		protected readonly Context $context,
		protected readonly array $item,
	) {
		$this->data = json_decode($item['data'], true);
		$this->children = $item['children'];
	}

	public function rewind(): void
	{
		reset($this->children);
	}

	public function current(): MenuItem
	{
		return new MenuItem($this->context, current($this->children));
	}

	public function key(): string
	{
		return key($this->children);
	}

	public function next(): void
	{
		next($this->children);
	}

	public function valid(): bool
	{
		return key($this->children) !== null;
	}

	public function type(): string
	{
		return $this->data['type'];
	}

	public function title(): string
	{
		return $this->translated('title');
	}

	public function path(): string
	{
		return $this->translated('path');
	}

	public function image(): ?string
	{
		$image = $this->data['image'] ?? null;

		if (!$image) {
			return null;
		}

		return sprintf('/assets/menu/%s/%s', $this->item['menu'], $image);
	}

	public function class(): ?string
	{
		return $this->data['class'] ?? null;
	}

	public function level(): int
	{
		return $this->item['level'];
	}

	public function children(): Generator
	{
		foreach ($this->children as $child) {
			yield new MenuItem($this->context, $child);
		}
	}

	public function setChildren(array $children): void
	{
		$this->children = $children;
	}

	public function hasChildren(): bool
	{
		return count($this->children) > 0;
	}

	protected function translated(string $key): string
	{
		$locale = $this->context->locale();

		while ($locale) {
			$value = $this->data[$key][$locale->id] ?? null;

			if ($value) {
				return $value;
			}

			$locale = $locale->fallback();
		}

		return '';
	}
}
