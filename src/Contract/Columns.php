<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Column;

/**
 * Replaces the default listing columns of a collection. Spread
 * Column::defaults() to extend them instead.
 */
interface Columns
{
	/** @return list<Column> */
	public function columns(): array;
}
