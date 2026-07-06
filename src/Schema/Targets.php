<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * Restricts a Reference field to the given node types. Types may be
 * given as class-strings or as node type handles; the picker's search
 * endpoint resolves either to a handle when it constrains the query.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Targets
{
	/** @var list<string> */
	public array $types;

	public function __construct(string ...$types)
	{
		$this->types = $types;
	}
}
