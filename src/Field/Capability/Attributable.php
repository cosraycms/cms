<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

interface Attributable extends ItemRequirable
{
	public function attribution(bool $attribution = true): static;

	public function hasAttribution(): bool;
}
