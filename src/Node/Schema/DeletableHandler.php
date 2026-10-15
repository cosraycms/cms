<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Deletable;

class DeletableHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Deletable, 'The registry maps #[Deletable] to this handler');

		return ['deletable' => $meta->value];
	}
}
