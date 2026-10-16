<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Schema\Badge;
use Override;

class BadgeHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $class): array
	{
		assert($meta instanceof Badge, 'The registry maps #[Badge] to this handler');

		return ['badge' => $meta->badge];
	}
}
