<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class CustomIcon
{
	public function __construct(
		public string $value,
	) {}
}
