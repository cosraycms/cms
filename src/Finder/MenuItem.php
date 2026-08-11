<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Context;
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

	/**
	 * Node items linked by uid follow their node's current path; the
	 * snapshot in `data` covers legacy rows and vanished nodes.
	 */
	public function path(): string
	{
		$resolved = $this->localized($this->nodePaths());

		return $resolved !== '' ? $resolved : $this->translated('path');
	}

	public function image(): ?string
	{
		$uid = $this->data['image'] ?? null;

		if (!$uid || !is_string($uid)) {
			return null;
		}

		return $this->context->assets()->get($uid)?->path();
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
		return $this->localized($this->data[$key] ?? null);
	}

	protected function localized(mixed $map): string
	{
		if (!is_array($map)) {
			return '';
		}

		$locale = $this->context->locale();

		while ($locale) {
			$value = $map[$locale->id] ?? null;

			if ($value) {
				return $value;
			}

			$locale = $locale->fallback();
		}

		return '';
	}

	/** @return ?array<string, string> */
	private function nodePaths(): ?array
	{
		$paths = $this->item['node_paths'] ?? null;

		if (!is_string($paths)) {
			return null;
		}

		$decoded = json_decode($paths, true);

		return is_array($decoded) ? $decoded : null;
	}
}
