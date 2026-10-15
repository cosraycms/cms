<?php

declare(strict_types=1);

namespace Cosray;

class Category
{
	/** @param list<Category> $categories */
	public function __construct(
		public readonly string $name,
		public readonly string $title,
		public readonly array $categories,
	) {}
}
