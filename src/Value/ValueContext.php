<?php

declare(strict_types=1);

namespace Cosray\Value;

class ValueContext
{
	/** @param array<string, mixed> $data */
	public function __construct(
		public readonly string $fieldName,
		public readonly array $data,
	) {}
}
