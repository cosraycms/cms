<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Schema\Permission;

class PermissionHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		assert($meta instanceof Permission, 'The registry maps #[Permission] to this handler');

		return ['permission' => is_string($meta->value) ? $meta->value : null];
	}
}
