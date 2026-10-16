<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Override;

class HiddenHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $class): array
	{
		return ['hidden' => true];
	}
}
