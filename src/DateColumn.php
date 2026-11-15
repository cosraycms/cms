<?php

declare(strict_types=1);

namespace Cosray;

use Closure;
use Cosray\Finder\SortField;
use Cosray\Node\Wrapper;
use Override;

/**
 * A text column whose values the listing formats as dates, with the time
 * or without, in the panel's locale, made by Column::date(),
 * Column::dateTime(), Column::changed() or Column::created(). Sorting by a
 * key without fields orders by date or by instant.
 */
final class DateColumn extends TextColumn
{
	/** @param string|Closure(Wrapper): mixed $field */
	public function __construct(
		string $title,
		string|Closure $field,
		public readonly bool $time = false,
	) {
		parent::__construct($title, $field);
	}

	#[Override]
	public function kind(): string
	{
		return $this->time ? 'datetime' : 'date';
	}

	#[Override]
	protected function sortField(string $key): string|SortField
	{
		return match (true) {
			$this->meta() => $key,
			$this->time => SortField::dateTime($key),
			default => SortField::date($key),
		};
	}
}
