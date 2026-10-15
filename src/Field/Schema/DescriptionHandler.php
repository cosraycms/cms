<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;

class DescriptionHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		$field->description($meta->description);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['description' => $field->getDescription()];
	}
}
