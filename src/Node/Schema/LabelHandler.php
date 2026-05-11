<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

class LabelHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		return ['label' => $meta->label];
	}
}
