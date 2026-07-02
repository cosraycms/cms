<?php

declare(strict_types=1);

namespace Cosray\Collection;

use Cosray\Exception\RuntimeException;
use Cosray\NavigationItem;
use Cosray\NavMeta;
use Override;

/**
 * Navigation entry for a collection, resolved from the collection's
 * schema without instantiating the collection class.
 */
final class Ref implements NavigationItem
{
	/**
	 * @param class-string<\Cosray\Collection> $class
	 */
	public function __construct(
		public readonly string $class,
		public readonly NavMeta $meta,
		public readonly string $handle,
	) {}

	#[Override]
	public function slug(): ?string
	{
		return $this->handle;
	}

	/** @return list<NavigationItem> */
	#[Override]
	public function children(): array
	{
		return [];
	}

	/** @param array<array-key, mixed> $args */
	public function icon(string $id, array $args = []): static
	{
		$id = trim($id);

		if ($id === '') {
			throw new RuntimeException('Collection icon ids must not be empty');
		}

		$this->meta->icon = [
			'id' => $id,
			'args' => $args,
		];

		return $this;
	}
}
