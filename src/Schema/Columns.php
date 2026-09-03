<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Turns a Blocks field into a grid of `$columns` columns. Without the
 * attribute a blocks field is a stacked list without layout controls.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Columns
{
	public function __construct(
		public int $columns,
		public int $min = 1,
		public Responsive $responsive = Responsive::Stack,
	) {}
}
