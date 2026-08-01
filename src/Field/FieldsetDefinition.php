<?php

declare(strict_types=1);

namespace Cosray\Field;

final readonly class FieldsetDefinition
{
	/**
	 * @param list<string> $fields
	 */
	public function __construct(
		public string $name,
		public ?string $label,
		public ?string $description,
		public int $width,
		public array $fields,
	) {}
}
