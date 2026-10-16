<?php

declare(strict_types=1);

namespace Cosray\Collection\Schema;

use Cosray\Schema\Blueprints;
use Override;

class BlueprintsHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $class): array
	{
		assert($meta instanceof Blueprints, 'The registry maps #[Blueprints] to this handler');

		return ['blueprints' => $meta->types];
	}
}
