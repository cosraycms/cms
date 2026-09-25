<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Exception\RuntimeException;

class TypesHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		$types = array_map(trim(...), $meta->types);

		if ($types === [] || in_array('', $types, true)) {
			throw new RuntimeException("#[Types] of collection '{$class}' must name at least one node type");
		}

		return ['types' => $types];
	}
}
