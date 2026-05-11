<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class DefaultValue
{
	public function __construct(
		public mixed $default,
	) {}
}
