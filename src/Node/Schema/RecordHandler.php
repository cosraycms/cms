<?php

declare(strict_types=1);

namespace Cosray\Node\Schema;

use Cosray\Schema\Record;
use Override;

class RecordHandler extends Handler
{
	#[Override]
	public function resolve(object $meta, string $nodeClass): array
	{
		assert($meta instanceof Record, 'The registry maps #[Record] to this handler');

		return ['record' => true];
	}
}
