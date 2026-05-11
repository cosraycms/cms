<?php

declare(strict_types=1);

namespace Celemas\Cms\Node\Schema;

class IconHandler extends Handler
{
	public function resolve(object $meta, string $nodeClass): array
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
