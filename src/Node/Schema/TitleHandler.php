<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

use Celemas\Cms\Exception\RuntimeException;

class TitleHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
	{
		if ($meta->field === '') {
			throw new RuntimeException(
				"The #[Title] attribute on node '{$nodeClass}' requires a non-empty field name when used on a class.",
			);
		}

		return ['titleField' => $meta->field];
	}
}
