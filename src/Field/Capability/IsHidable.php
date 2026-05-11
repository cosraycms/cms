<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

trait IsHidable
{
	protected bool $hidden = false;

	public function hidden(bool $hidden = true): static
	{
		$this->hidden = $hidden;

		return $this;
	}

	public function getHidden(): bool
	{
		return $this->hidden;
	}
}
