<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;
use Cosray\Exception\RuntimeException;

/**
 * The node types allowed as direct children. `sortable` gives the children
 * a manual order: editors arrange them in the panel, and `children()`
 * returns them in that order.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Children
{
	/** @var list<class-string> */
	public array $types;

	/** @param class-string|list<class-string> $types */
	public function __construct(
		string|array $types,
		public bool $sortable = false,
	) {
		$types = is_string($types) ? [$types] : array_values($types);

		foreach ($types as $type) {
			if (!is_string($type) || trim($type) === '') {
				throw new RuntimeException('#[Children] needs non-empty class names');
			}
		}

		$this->types = $types;
	}
}
