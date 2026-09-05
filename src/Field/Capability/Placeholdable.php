<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Placeholdable
{
	public function placeholder(string $placeholder): static;

	public function getPlaceholder(): ?string;
}
