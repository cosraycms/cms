<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\Description;

class DescriptionHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Description, 'The registry maps #[Description] to this handler');

		$field->description($meta->description);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['description' => $field->getDescription()];
	}
}
