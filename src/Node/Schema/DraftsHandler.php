<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Drafts;
use Override;

class DraftsHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Drafts, 'The registry maps #[Drafts] to this handler');

		return ['drafts' => $meta->value];
	}
}
