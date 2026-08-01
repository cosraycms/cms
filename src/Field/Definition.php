<?php

declare(strict_types=1);

namespace Cosray\Field;

use ReflectionProperty;

final readonly class Definition
{
	/**
	 * @param class-string<Field> $type
	 */
	public function __construct(
		public string $name,
		public string $type,
		public ReflectionProperty $property,
		public ?string $embedded = null,
	) {}
}
