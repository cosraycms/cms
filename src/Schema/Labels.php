<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Keeps the sub-field labels of a block type. A block with a single
 * visible field renders it without its label, since the block already
 * says what it is; a type that wants them back declares this.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Labels
{
	public function __construct(
		public readonly bool $value = true,
	) {}
}
