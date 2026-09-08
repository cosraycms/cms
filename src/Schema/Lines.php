<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Lines
{
	public function __construct(
		public int $lines,
	) {}
}
