<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Schema\Icon;
use Override;

class IconHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $class): array
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
