<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\FieldOrder;
use Override;

class FieldOrderHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof FieldOrder, 'The registry maps #[FieldOrder] to this handler');

		return ['fieldOrder' => $meta->fields];
	}
}
