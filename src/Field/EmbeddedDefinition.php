<?php

declare(strict_types=1);

namespace Cosray\Field;

use ReflectionProperty;

final readonly class EmbeddedDefinition
{
	/**
	 * @param class-string $type
	 * @param array<string, Definition> $fields
	 */
	public function __construct(
		public string $name,
		public string $type,
		public ReflectionProperty $property,
		public array $fields,
		public ?FieldsetDefinition $fieldset,
	) {}
}
