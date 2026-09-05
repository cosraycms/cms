<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Placeholder
{
	public function __construct(
		public string $placeholder,
	) {}
}
