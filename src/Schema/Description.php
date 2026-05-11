<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Description
{
	public function __construct(
		public string $description,
	) {}
}
