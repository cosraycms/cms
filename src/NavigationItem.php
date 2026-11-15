<?php

declare(strict_types=1);

namespace Cosray;

use Closure;

interface NavigationItem
{
	public NavMeta $meta { get; }

	public function slug(): ?string;

	/**
	 * @param (Closure(string): bool)|null $permits leaves out the entries whose permission it denies
	 * @return list<NavigationItem>
	 */
	public function children(?Closure $permits = null): array;
}
