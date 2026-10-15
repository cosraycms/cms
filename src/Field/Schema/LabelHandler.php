<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;

class LabelHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		$field->label($meta->label);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['label' => $field->getLabel()];
	}
}
