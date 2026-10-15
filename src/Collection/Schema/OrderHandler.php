<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Schema\Order;

class OrderHandler extends Handler
{
	public function resolve(object $meta, string $class): array
	{
		assert($meta instanceof Order, 'The registry maps #[Order] to this handler');

		return ['order' => $meta->order];
	}
}
