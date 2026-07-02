<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

class PermissionHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		return ['permission' => is_string($meta->value) ? $meta->value : null];
	}
}
