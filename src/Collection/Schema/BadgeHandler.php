<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Schema\Badge;

class BadgeHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		assert($meta instanceof Badge, 'The registry maps #[Badge] to this handler');

		return ['badge' => $meta->badge];
	}
}
