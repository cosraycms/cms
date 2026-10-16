<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Handle;
use Override;

class HandleHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Handle, 'The registry maps #[Handle] to this handler');

		return ['handle' => $meta->value];
	}
}
