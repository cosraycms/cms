<?php

declare(strict_types=1);

namespace Cosray\Assets;

final readonly class LibraryPage
{
	/**
	 * @param list<Asset> $assets
	 * @param array<string, int> $counts per filter kind, see Library::page()
	 */
	public function __construct(
		public array $assets,
		public int $page,
		public bool $more,
		/** Full match count across all pages; 0 when paging past the end. */
		public int $total,
		public array $counts,
	) {}
}
