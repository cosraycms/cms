<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\Validate;

class ValidateHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Validate, 'The registry maps #[Validate] to this handler');

		$field->addValidators(...$meta->validators);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['validators' => $field->validators()];
	}
}
