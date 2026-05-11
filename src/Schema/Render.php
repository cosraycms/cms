<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;

#[Attribute]
class Render
{
	public function __construct(
		public readonly string $value,
	) {}
}
