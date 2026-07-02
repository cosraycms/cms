<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

class HiddenHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		return ['hidden' => true];
	}
}
