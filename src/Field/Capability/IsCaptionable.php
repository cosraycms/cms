<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsCaptionable
{
	protected bool $caption = false;

	public function caption(bool $caption = true): static
	{
		$this->caption = $caption;

		return $this;
	}

	public function hasCaption(): bool
	{
		return $this->caption;
	}
}
