<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/** Editors can write a caption for each use of the field's images or videos. */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Caption
{
	public function __construct(
		public bool $required = false,
	) {}
}
