<?php

declare(strict_types=1);

namespace Cosray\Field;

final readonly class Hydration
{
	/**
	 * @param array<string, Field> $fields
	 * @param array<string, object> $embedded
	 */
	public function __construct(
		public array $fields,
		public array $embedded,
	) {}
}
