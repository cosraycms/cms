<?php

declare(strict_types=1);

namespace Cosray\Security;

/**
 * Allow entries only: a permission holds when any of the subject's
 * principals was allowed it, so the order of entries never matters.
 */
final class Acl
{
	/** @var array<string, array<string, true>> */
	private array $entries = [];

	public function allow(string $principal, string ...$permissions): self
	{
		foreach ($permissions as $permission) {
			$this->entries[$principal][$permission] = true;
		}

		return $this;
	}

	/** @param list<string> $principals */
	public function permits(array $principals, string $permission): bool
	{
		foreach ($principals as $principal) {
			if (isset($this->entries[$principal][$permission])) {
				return true;
			}
		}

		return false;
	}
}
