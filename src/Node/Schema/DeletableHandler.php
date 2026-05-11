<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

class DeletableHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		return ['deletable' => $meta->value];
	}
}
