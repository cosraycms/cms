<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Filterable
{
	public function filter(string $query): static;

	public function getFilter(): ?string;
}
