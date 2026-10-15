<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;

class RequiredHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		$field->required();
	}

	public function properties(object $meta, Field $field): array
	{
		return ['required' => $field->isRequired()];
	}
}
