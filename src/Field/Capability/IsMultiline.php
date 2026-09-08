<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsMultiline
{
	protected ?int $lines = null;

	public function lines(int $lines): static
	{
		$this->lines = $lines;

		return $this;
	}

	public function getLines(): ?int
	{
		return $this->lines;
	}
}
