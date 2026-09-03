<?php

declare(strict_types=1);

namespace Cosray\Field\Capability\Blocks;

use Cosray\Schema\Responsive;

interface Resizable
{
	public function columns(int $columns, int $min = 1, Responsive $responsive = Responsive::Stack): static;

	public function getColumns(): int;

	public function getMin(): int;

	public function getResponsive(): Responsive;
}
