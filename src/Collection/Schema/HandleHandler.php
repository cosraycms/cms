<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

class HandleHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		return ['handle' => $meta->value];
	}
}
