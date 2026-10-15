<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Schema\Title;

class TitleHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Title, 'The registry maps #[Title] to this handler');

		if ($meta->field === '') {
			throw new RuntimeException(
				"The #[Title] attribute on node '{$nodeClass}' requires a non-empty field name when used on a class.",
			);
		}

		return ['titleField' => $meta->field];
	}
}
