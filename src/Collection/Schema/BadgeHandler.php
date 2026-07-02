<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

class BadgeHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		return ['badge' => $meta->badge];
	}
}
