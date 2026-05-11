<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;

#[Attribute]
class Handle
{
	public function __construct(
		public readonly string $value,
	) {}
}
