<?php

declare(strict_types=1);

namespace Cosray\Fulltext;

final readonly class Search
{
	public function __construct(
		public float $score,
		public Snippet $snippet,
	) {}
}
