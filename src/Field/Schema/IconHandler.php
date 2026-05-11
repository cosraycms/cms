<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Field\Field;

class IconHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		// Icons only contribute metadata for panel payloads.
	}

	public function properties(object $meta, Field $field): array
	{
		$id = trim((string) $meta->id);

		if ($id === '') {
			return ['icon' => null];
		}

		return [
			'icon' => [
				'id' => $id,
				'args' => $meta->args,
			],
		];
	}
}
