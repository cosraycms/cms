<?php

declare(strict_types=1);

namespace Cosray\Icons;

interface Provider
{
	/** @param array<array-key, mixed> $args */
	public function icon(
		string $id,
		array $args = [],
	): string;
}
