<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Exception\RuntimeException;
use Override;

/**
 * Navigation entry pointing at an arbitrary URL — the nav item type
 * for plugin apps with their own panel pages.
 */
final class NavLink implements NavigationItem
{
	#[Override]
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
	public function children(?Closure $permits = null): array
	{
		return [];
	}

	/**
	 * Whether the link is current on a path: on its URL and below it, or
	 * below `activePrefix` when it has one. When several links of a rail
	 * match, the rail marks the longest match, see match().
	 */
	public function active(string $currentPath): bool
	{
		return $this->match($currentPath) !== null;
	}

	/** The length of the matching URL or prefix, null when the link is not current on the path. */
	public function match(string $currentPath): ?int
	{
		if ($this->activePrefix !== null) {
			return str_starts_with($currentPath, $this->activePrefix) ? strlen($this->activePrefix) : null;
		}

		$url = rtrim((string) parse_url($this->url, PHP_URL_PATH), '/');

		if ($currentPath === $url || $currentPath === $url . '/' || str_starts_with($currentPath, $url . '/')) {
			return strlen($url);
		}

		return null;
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

	/** Hides the link from users without the permission; its route must still enforce it. */
	public function permission(string $permission): static
	{
		$permission = trim($permission);

		if ($permission === '') {
			throw new RuntimeException('Nav link permissions must not be empty');
		}

		$this->meta->permission = $permission;

		return $this;
	}
}
