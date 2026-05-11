<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

abstract class Handler
{
	/**
	 * Resolve schema properties from an attribute instance.
	 *
	 * @param class-string $nodeClass
	 * @return array<string, mixed>
	 */
	abstract public function resolve(object $meta, string $nodeClass): array;
}
