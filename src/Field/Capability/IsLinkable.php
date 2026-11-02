<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsLinkable
{
	protected bool $linkable = false;

	public function linkable(bool $linkable = true): static
	{
		$this->linkable = $linkable;

		return $this;
	}

	public function isLinkable(): bool
	{
		return $this->linkable;
	}
}
