<?php

declare(strict_types=1);

namespace Cosray\Schema;

use Attribute;

/**
 * The roles a user type can be given. Without the attribute every role the
 * app defines is assignable; `#[Roles]` without arguments allows none.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Roles
{
	/** @var list<string> */
	public array $roles;

	public function __construct(string ...$roles)
	{
		$this->roles = array_values($roles);
	}
}
