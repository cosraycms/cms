<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Children
{
	/** @var list<class-string> */
	public readonly array $types;

	public function __construct(string ...$types)
	{
		$this->types = $types;
	}
}
