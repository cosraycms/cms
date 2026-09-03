<?php

declare(strict_types=1);

namespace Cosray\Field\Capability\Blocks;

use Cosray\Schema\Responsive;
use ValueError;

trait IsResizable
{
	protected int $columns = 1;
	protected int $min = 1;
	protected Responsive $responsive = Responsive::Stack;

	public function columns(int $columns, int $min = 1, Responsive $responsive = Responsive::Stack): static
	{
		if ($columns < 1 || $columns > 25) {
			throw new ValueError('The value of $columns must be >= 1 and <= 25');
		}

		if ($min < 1 || $min > $columns) {
			throw new ValueError('The value of $min must be >= 1 and <= ' . (string) $columns);
		}

		$this->columns = $columns;
		$this->min = $min;
		$this->responsive = $responsive;

		return $this;
	}

	public function getColumns(): int
	{
		return $this->columns;
	}

	public function getMin(): int
	{
		return $this->min;
	}

	public function getResponsive(): Responsive
	{
		return $this->responsive;
	}
}
