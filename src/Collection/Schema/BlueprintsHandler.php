<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

class BlueprintsHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		return ['blueprints' => $meta->types];
	}
}
