<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Whether edits of a node type wait in a working copy until they are
 * applied. `#[Drafts(false)]` saves them to the main version at once,
 * for data such as picklists or settings; a `#[Record]` has no drafts.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Drafts
{
	public function __construct(
		public bool $value = true,
	) {}
}
