<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Tools
{
	/** @var list<Tool> */
	public array $tools;

	public function __construct(Tool ...$tools)
	{
		$this->tools = array_values($tools);
	}
}
