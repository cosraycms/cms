<?php

declare(strict_types=1);

namespace Cosray;

/**
 * What sites and plugins add to the system area's rail, between the users
 * and the profile. Links only, unlike a Section: collection screens
 * always belong to the content area.
 */
final class SystemNav
{
	/** @var list<NavLink> */
	private array $links = [];

	public function link(string $label, string $url, ?string $activePrefix = null): NavLink
	{
		$link = new NavLink($label, $url, $activePrefix);
		$this->links[] = $link;

		return $link;
	}

	/** @return list<NavLink> */
	public function links(): array
	{
		$visible = array_values(array_filter($this->links, static fn(NavLink $link): bool => !$link->meta->hidden));
		usort($visible, static fn(NavLink $left, NavLink $right): int => $left->meta->order <=> $right->meta->order);

		return $visible;
	}
}
