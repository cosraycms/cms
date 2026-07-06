<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Targetable
{
	public function target(string ...$types): static;

	/** @return list<string> */
	public function targets(): array;
}
