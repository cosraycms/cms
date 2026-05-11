<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Capability\Grid;

interface Resizable
{
	public function columns(int $columns, int $minCellWidth = 1): static;
}
