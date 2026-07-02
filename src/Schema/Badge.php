<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Badge
{
	public function __construct(
		public string $badge,
	) {}
}
