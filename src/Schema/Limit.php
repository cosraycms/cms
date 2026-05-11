<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Limit
{
	public function __construct(
		public int $max,
		public int $min = 0,
	) {}
}
