<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

class RouteHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		return [
			'route' => $meta->value,
			'routable' => true,
		];
	}
}
