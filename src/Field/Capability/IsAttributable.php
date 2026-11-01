<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsAttributable
{
	protected bool $attribution = false;

	public function attribution(bool $attribution = true): static
	{
		$this->attribution = $attribution;

		return $this;
	}

	public function hasAttribution(): bool
	{
		return $this->attribution;
	}
}
