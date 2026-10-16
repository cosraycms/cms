<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Permission;
use Override;

class PermissionHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Permission, 'The registry maps #[Permission] to this handler');

		return ['permission' => $meta->value];
	}
}
