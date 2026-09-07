<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Common
{
	public array $types;

	public function __construct(string ...$types)
	{
		$this->types = $types;
	}
}
