<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/** Every image of the field needs an alt text, its own or the media screen's. */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Alt
{
	public function __construct(
		public bool $required = false,
	) {}
}
