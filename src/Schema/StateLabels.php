<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class StateLabels
{
	public function __construct(
		public ?string $true = null,
		public ?string $false = null,
		public ?string $null = null,
	) {}
}
