<?php

declare(strict_types=1);

namespace Cosray;

use Override;

/**
 * A text column whose values the listing formats as dates, made by
 * Column::date(), Column::changed() or Column::created(). Sorting by it
 * orders by the declared sort fields, not by the formatted text.
 */
final class DateColumn extends TextColumn
{
	#[Override]
	public function kind(): string
	{
		return 'date';
	}
}
