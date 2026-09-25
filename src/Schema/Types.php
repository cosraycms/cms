<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * The node types a collection lists, as handles or classes. The panel lists
 * them in any state, unpublished and hidden entries included.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Types
{
	/** @var list<string> */
	public array $types;

	public function __construct(string ...$types)
	{
		$this->types = array_values($types);
	}
}
