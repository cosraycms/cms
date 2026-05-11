<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

class ChildrenHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		return ['children' => $meta->types];
	}
}
