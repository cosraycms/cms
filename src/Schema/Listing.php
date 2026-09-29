<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * How the panel lists a collection. `sortable` gives the collection's top
 * level a manual order of its own, independent of any other collection:
 * every entry of a flat listing, the root nodes of a `children` listing.
 * Children always follow their parent's #[Children] declaration.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Listing
{
	/** @param list<string> $search Fields the panel search matches */
	public function __construct(
		public bool $published = true,
		public bool $locked = false,
		public bool $hidden = false,
		public bool $children = false,
		public array $search = ['uid', 'title'],
		public bool $sortable = false,
	) {}
}
