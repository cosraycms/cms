<?php

declare(strict_types=1);

namespace Cosray\Panel;

final class CollectionPager
{
	private function __construct(
		public readonly int $total,
		public readonly int $pageCount,
		public readonly int $currentPage,
		public readonly int $rangeStart,
		public readonly int $rangeEnd,
		public readonly ?string $previousUrl,
		public readonly ?string $nextUrl,
	) {}

	public static function from(int $total, int $rowCount, CollectionUrls $urls): self
	{
		$query = $urls->query;
		$pageCount = $query->limit > 0 ? max(1, (int) ceil($total / $query->limit)) : 1;
		$currentPage = $query->limit > 0
			? min($pageCount, (int) floor($query->offset / $query->limit) + 1)
			: 1;

		return new self(
			total: $total,
			pageCount: $pageCount,
			currentPage: $currentPage,
			rangeStart: $total === 0 ? 0 : min($query->offset + 1, $total),
			rangeEnd: min($query->offset + $rowCount, $total),
			previousUrl: $query->offset > 0
				? $urls->collection(['offset' => max(0, $query->offset - $query->limit)])
				: null,
			nextUrl: ($query->offset + $query->limit) < $total
				? $urls->collection(['offset' => $query->offset + $query->limit])
				: null,
		);
	}
}
