<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Attributable
{
	public function attribution(bool $attribution = true): static;

	public function hasAttribution(): bool;
}
