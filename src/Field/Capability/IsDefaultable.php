<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

trait IsDefaultable
{
	protected mixed $default = null;

	public function default(mixed $default): static
	{
		$this->default = $default;

		return $this;
	}

	public function getDefault(): mixed
	{
		return $this->default;
	}
}
