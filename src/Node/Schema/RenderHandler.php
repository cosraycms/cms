<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Render;

class RenderHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Render, 'The registry maps #[Render] to this handler');

		return [
			'renderer' => $meta->value,
			'renderable' => true,
		];
	}
}
