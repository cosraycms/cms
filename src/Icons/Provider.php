<?php

declare(strict_types=1);

namespace Cosray\Icons;

interface Provider
{
	public function icon(
		string $id,
		array $args = [],
	): string;
}
