<?php

declare(strict_types=1);

namespace Cosray\Contract;

use Cosray\Column;
use Cosray\StatusColumn;

/**
 * Replaces the default listing columns of a collection. Spread
 * Column::defaults() to extend them instead. Without a Column::status(),
 * the stages follow the first column.
 */
interface Columns
{
	/** @return list<Column|StatusColumn> */
	public function columns(): array;
}
