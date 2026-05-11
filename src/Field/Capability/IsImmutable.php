<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

trait IsImmutable
{
	protected bool $immutable = false;

	public function immutable(bool $immutable = true): static
	{
		$this->immutable = $immutable;

		return $this;
	}

	public function getImmutable(): bool
	{
		return $this->immutable;
	}
}
