<?php

declare(strict_types=1);

namespace Celemas\Cms;

use Celemas\Cms\Exception\RuntimeException;
use Closure;

final class Navigation
{
	private readonly Section $root;

	/** @var array<string, Collection> */
	private array $collections = [];

	public function __construct()
	{
		$this->root = new Section('_root', Closure::fromCallable([$this, 'register']));
	}

	public function section(string $label): Section
	{
		return $this->root->section($label);
	}

	/** @param class-string<Collection> $class */
	public function collection(string $class): Collection
	{
		return $this->root->collection($class);
	}

	/** @return list<NavigationItem> */
	public function children(): array
	{
		return $this->root->children();
	}

	/** @return list<NavigationItem> */
	public function items(): array
	{
		return $this->children();
	}

	/** @return array<string, Collection> */
	public function refs(): array
	{
		return $this->collections;
	}

	public function ref(string $handle): Collection
	{
		if (!isset($this->collections[$handle])) {
			throw new RuntimeException('Unknown collection handle: ' . $handle);
		}

		return $this->collections[$handle];
	}

	public function payload(): array
	{
		return $this->serialize($this->items());
	}

	private function register(Collection $collection): void
	{
		$handle = $collection->slug();
		assert(is_string($handle), 'Collection slugs must be strings');

		if (isset($this->collections[$handle])) {
			throw new RuntimeException('Duplicate collection handle: ' . $handle);
		}

		$this->collections[$handle] = $collection;
	}

	/**
	 * @param list<NavigationItem> $items
	 * @return list<array<string, mixed>>
	 */
	private function serialize(array $items): array
	{
		$result = [];

		foreach ($items as $item) {
			if ($item instanceof Section) {
				$result[] = [
					'type' => 'section',
					'name' => $item->meta->label,
					'meta' => $item->meta->array(),
					'children' => $this->serialize($item->children()),
				];

				continue;
			}

			$result[] = [
				'type' => 'collection',
				'slug' => $item->slug(),
				'name' => $item->meta->label,
				'meta' => $item->meta->array(),
				'children' => [],
			];
		}

		return $result;
	}
}
