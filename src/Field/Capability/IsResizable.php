<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability;

trait IsResizable
{
	protected ?int $width = null;
	protected ?int $rows = null;

	public function width(int $width): static
	{
		$this->width = $width;

		return $this;
	}

	public function getWidth(): int
	{
		return $this->width;
	}

	public function rows(int $rows): static
	{
		$this->rows = $rows;

		return $this;
	}

	public function getRows(): int
	{
		return $this->rows;
	}
}
