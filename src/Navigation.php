<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Collection\Ref;
use Cosray\Collection\Schemas;
use Cosray\Exception\RuntimeException;

final class Navigation
{
	private readonly Section $root;

	/** @var array<string, Ref> */
	private array $collections = [];

	public function __construct(?Schemas $schemas = null)
	{
		$this->root = new Section('_root', Closure::fromCallable([$this, 'register']), $schemas);
	}

	public function section(string $label): Section
	{
		return $this->root->section($label);
	}

	/** @param class-string<Collection> $class */
	public function collection(string $class): Ref
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

	/** @return array<string, Ref> */
	public function refs(): array
	{
		return $this->collections;
	}

	public function ref(string $handle): Ref
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

	private function register(Ref $ref): void
	{
		if (isset($this->collections[$ref->handle])) {
			throw new RuntimeException('Duplicate collection handle: ' . $ref->handle);
		}

		$this->collections[$ref->handle] = $ref;
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
					'name' => __($item->meta->label),
					'meta' => $item->meta->array(),
					'children' => $this->serialize($item->children()),
				];

				continue;
			}

			if ($item instanceof NavLink) {
				$result[] = [
					'type' => 'link',
					'url' => $item->url,
					'name' => __($item->meta->label),
					'meta' => $item->meta->array(),
					'children' => [],
				];

				continue;
			}

			$result[] = [
				'type' => 'collection',
				'slug' => $item->slug(),
				'name' => __($item->meta->label),
				'meta' => $item->meta->array(),
				'children' => [],
			];
		}

		return $result;
	}
}
