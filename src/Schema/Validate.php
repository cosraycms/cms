<?php

declare(strict_types=1);

namespace Celemas\Cms\Schema;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Validate
{
	/** @var string[] */
	public array $validators;

	public function __construct(string ...$validators)
	{
		$this->validators = $validators;
	}
}
