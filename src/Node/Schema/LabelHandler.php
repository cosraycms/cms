<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Label;

class LabelHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Label, 'The registry maps #[Label] to this handler');

		return ['label' => $meta->label];
	}
}
