<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

/**
 * A word or quoted phrase of a search query. `inside` lets it match inside
 * longer words; otherwise it matches whole words only.
 */
final readonly class Term
{
	public function __construct(
		public string $text,
		public bool $negated = false,
		public bool $phrase = false,
		public bool $inside = false,
	) {}
}
