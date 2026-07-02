<?php

declare(strict_types=1);

namespace Cosray;

use Cosray\Exception\RuntimeException;
use Override;

/**
 * Navigation entry pointing at an arbitrary URL — the nav item type
 * for plugin apps with their own panel pages.
 */
final class NavLink implements NavigationItem
{
	public readonly NavMeta $meta;

	public function __construct(
		string $label,
		public readonly string $url,
		public readonly ?string $activePrefix = null,
	) {
		$label = trim($label);

		if ($label === '') {
			throw new RuntimeException('Nav link labels must not be empty');
		}

		$this->meta = new NavMeta($label);
	}

	#[Override]
	public function slug(): ?string
	{
		return null;
	}

	/** @return list<NavigationItem> */
	#[Override]
	public function children(): array
	{
		return [];
	}

	public function active(string $currentPath): bool
	{
		if ($this->activePrefix !== null) {
			return str_starts_with($currentPath, $this->activePrefix);
		}

		return $currentPath === $this->url;
	}

	/** @param array<array-key, mixed> $args */
	public function icon(string $id, array $args = []): static
	{
		$id = trim($id);

		if ($id === '') {
			throw new RuntimeException('Nav link icon ids must not be empty');
		}

		$this->meta->icon = [
			'id' => $id,
			'args' => $args,
		];

		return $this;
	}
}
