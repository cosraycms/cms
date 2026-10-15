<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Schema\Handle;

class HandleHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		assert($meta instanceof Handle, 'The registry maps #[Handle] to this handler');

		return ['handle' => $meta->value];
	}
}
