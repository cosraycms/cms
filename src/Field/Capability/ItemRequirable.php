<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

/** Parts of each item in a media field's value that must be filled. */
interface ItemRequirable
{
	public function requireItem(string $key): static;

	/** @return list<string> */
	public function requiredItems(): array;
}
