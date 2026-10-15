<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\Icon;

class IconHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		// Icons only contribute metadata for panel payloads.
	}

	public function properties(object $meta, Field $field): array
	{
		assert($meta instanceof Icon, 'The registry maps #[Icon] to this handler');

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
