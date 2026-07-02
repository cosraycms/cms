<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Order
{
	public function __construct(
		public int $order,
	) {}
}
