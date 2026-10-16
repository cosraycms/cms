<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Label;
use Override;

class LabelHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Label, 'The registry maps #[Label] to this handler');

		return ['label' => $meta->label];
	}
}
