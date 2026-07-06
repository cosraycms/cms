<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Narrows a Reference field's pickable nodes with a finder query, e.g.
 * #[Filter("type = 'beer' & productLine = 'klassiker'")]. The string is
 * the finder DSL (single `=`, `&`, `|`), applied server-side.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Filter
{
	public function __construct(
		public string $query,
	) {}
}
