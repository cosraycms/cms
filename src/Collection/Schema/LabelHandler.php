<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

class LabelHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		return ['label' => $meta->label];
	}
}
