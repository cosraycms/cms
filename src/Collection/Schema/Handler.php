<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

abstract class Handler
{
	/**
	 * Resolve schema properties from an attribute instance.
	 *
	 * @param class-string $class
	 * @return array<string, mixed>
	 */
	abstract public function resolve(object $meta, string $class): array;
}
