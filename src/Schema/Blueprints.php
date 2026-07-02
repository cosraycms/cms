<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Blueprints
{
	/** @var list<class-string> */
	public array $types;

	/** @param class-string ...$types */
	public function __construct(string ...$types)
	{
		$this->types = array_values($types);
	}
}
