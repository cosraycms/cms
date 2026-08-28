<?php

declare(strict_types=1);

namespace Cosray\Finder;

use Cosray\Context;
use Cosray\Field\Field;
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

	public function id(): string
	{
		return (string) $this->item['item'];
	}

	public function type(): string
	{
		return $this->data['type'];
	}

	/**
	 * Node items linked by uid inherit their node's current title when
	 * no title is stored; a stored title always overrides.
	 */
	public function title(): string
	{
		$title = $this->translated('title');

		return $title !== '' ? $title : $this->localized($this->joined('node_title'));
	}

	/**
	 * Node items linked by uid follow their node's current path; the
	 * snapshot in `data` covers legacy rows and vanished nodes.
	 */
	public function path(): string
	{
		$resolved = $this->localized($this->joined('node_paths'));

		return $resolved !== '' ? $resolved : $this->translated('path');
	}

	/**
	 * The link target for anchored item types, null for plain labels:
	 * `node` and `url` items link their path, `asset` items their file.
	 */
	public function href(): ?string
	{
		return match ($this->type()) {
			'node', 'url' => $this->path() ?: null,
			'asset' => $this->assetPath(),
			default => null,
		};
	}

	public function target(): ?string
	{
		$target = $this->data['target'] ?? null;

		return is_string($target) && $target !== '' ? $target : null;
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

		// A language-neutral value applies when no locale in the chain
		// matches, mirroring the finder's field compilation.
		return $map[Field::NEUTRAL_LOCALE] ?? '';
	}

	private function assetPath(): ?string
	{
		$uid = $this->data['asset'] ?? null;

		if (!$uid || !is_string($uid)) {
			return null;
		}

		return $this->context->assets()->get($uid)?->path();
	}

	/**
	 * A locale map the read query joined onto the row as jsonb, decoded.
	 *
	 * @return ?array<string, string>
	 */
	private function joined(string $key): ?array
	{
		$value = $this->item[$key] ?? null;

		if (!is_string($value)) {
			return null;
		}

		$decoded = json_decode($value, true);

		return is_array($decoded) ? $decoded : null;
	}
}
