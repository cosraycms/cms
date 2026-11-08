<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Options
{
	/** @param array<string|array<string, mixed>> $options A list, or labels keyed by value where the field supports it */
	public function __construct(
		public array $options,
	) {}
}
