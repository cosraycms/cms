<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class FieldOrder
{
	/** @var string[] */
	public readonly array $fields;

	public function __construct(string ...$fields)
	{
		$this->fields = $fields;
	}
}
