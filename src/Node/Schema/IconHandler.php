<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Icon;

class IconHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
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
