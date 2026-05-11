<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

interface Validatable
{
	public function addValidators(string ...$validators): static;

	public function validators(): array;
}
