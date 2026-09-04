<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

class LabelsHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		return ['labels' => $meta->value];
	}
}
