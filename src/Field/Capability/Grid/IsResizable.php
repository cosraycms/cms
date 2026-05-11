<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability\Grid;

use ValueError;

trait IsResizable
{
	protected int $columns = 12;
	protected int $minCellWidth = 1;

	public function columns(int $columns, int $minCellWidth = 1): static
	{
		if ($columns < 1 || $columns > 25) {
			throw new ValueError('The value of $columns must be >= 1 and <= 25');
		}

		if ($minCellWidth < 1 || $minCellWidth > $columns) {
			throw new ValueError('The value of $minCellWidth must be >= 1 and <= ' . (string) $columns);
		}

		$this->columns = $columns;
		$this->minCellWidth = $minCellWidth;

		return $this;
	}

	public function getColumns(): int
	{
		return $this->columns;
	}

	public function getMinCellWidth(): int
	{
		return $this->minCellWidth;
	}
}
