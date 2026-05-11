<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

trait IsLabelable
{
	protected ?string $label = null;

	public function label(string $label): static
	{
		$this->label = $label;

		return $this;
	}

	public function getLabel(): ?string
	{
		return $this->label;
	}
}
