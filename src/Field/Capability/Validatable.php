<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Validatable
{
	public function addValidators(string ...$validators): static;

	/** @return list<string> */
	public function validators(): array;
}
