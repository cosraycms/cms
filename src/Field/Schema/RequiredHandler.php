<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Override;

class RequiredHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		$field->required();
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return ['required' => $field->isRequired()];
	}
}
