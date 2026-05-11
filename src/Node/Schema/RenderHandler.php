<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

class RenderHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		return [
			'renderer' => $meta->value,
			'renderable' => true,
		];
	}
}
