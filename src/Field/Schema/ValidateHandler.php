<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;

class ValidateHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		$field->addValidators(...$meta->validators);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['validators' => $field->validators()];
	}
}
