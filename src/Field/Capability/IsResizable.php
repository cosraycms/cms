<?php

declare(strict_types=1);

namespace Cosray\Field\Capability;

trait IsResizable
{
	protected ?int $width = null;
	protected ?int $rowspan = null;

	public function width(int $width): static
	{
		$this->width = $width;

		return $this;
	}

	public function getWidth(): int
	{
		return $this->width;
	}

	public function rowspan(int $rowspan): static
	{
		$this->rowspan = $rowspan;

		return $this;
	}

	public function getRowspan(): ?int
	{
		return $this->rowspan;
	}
}
