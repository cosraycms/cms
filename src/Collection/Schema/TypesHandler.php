<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Schema\Types;
use Override;

class TypesHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $class): array
	{
		assert($meta instanceof Types, 'The registry maps #[Types] to this handler');

		$types = array_map(trim(...), $meta->types);

		if ($types === [] || in_array('', $types, true)) {
			throw new RuntimeException("#[Types] of collection '{$class}' must name at least one node type");
		}

		return ['types' => $types];
	}
}
