<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Labels;
use Override;

class LabelsHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Labels, 'The registry maps #[Labels] to this handler');

		return ['labels' => $meta->value];
	}
}
