<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Children;

class ChildrenHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Children, 'The registry maps #[Children] to this handler');

		return [
			'children' => $meta->types,
			'sortableChildren' => $meta->sortable,
		];
	}
}
