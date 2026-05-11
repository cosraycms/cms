<?php

declare(strict_types=1);

namespace Celemas\Cms\Tests\Fixtures\Node;

use Celemas\Cms\Node\Schema\Handler;

class CustomIconHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		return ['icon' => $meta->value];
	}
}
