<?php

declare(strict_types=1);

namespace Cosray;

use Cosray\Finder\SortField;
use Override;

/**
 * A text column whose values the listing formats as numbers in the panel's
 * locale and aligns to the right, made by Column::number(). Sorting by a
 * key without fields orders numerically.
 */
final class NumberColumn extends TextColumn
{
	#[Override]
	public function kind(): string
	{
		return 'number';
	}

	#[Override]
	protected function sortField(string $key): string|SortField
	{
		return $this->meta() ? $key : SortField::number($key);
	}
}
