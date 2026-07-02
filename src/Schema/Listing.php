<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Listing
{
	public function __construct(
		public bool $published = true,
		public bool $locked = false,
		public bool $hidden = false,
		public bool $children = false,
	) {}
}
