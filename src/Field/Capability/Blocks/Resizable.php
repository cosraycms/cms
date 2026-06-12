<?php

declare(strict_types=1);

namespace Cosray\Field\Capability\Blocks;

interface Resizable
{
	public function columns(int $columns, int $minCellWidth = 1): static;
}
