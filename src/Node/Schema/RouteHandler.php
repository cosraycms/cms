<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Route;
use Override;

class RouteHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Route, 'The registry maps #[Route] to this handler');

		return [
			'route' => $meta->value,
			'routable' => true,
		];
	}
}
