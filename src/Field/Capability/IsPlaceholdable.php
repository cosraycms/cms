<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsPlaceholdable
{
	protected ?string $placeholder = null;

	public function placeholder(string $placeholder): static
	{
		$this->placeholder = $placeholder;

		return $this;
	}

	public function getPlaceholder(): ?string
	{
		return $this->placeholder;
	}
}
