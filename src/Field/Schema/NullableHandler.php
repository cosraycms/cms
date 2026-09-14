<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Checkbox;
use Cosray\Field\Field;

class NullableHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if (!$field instanceof Checkbox) {
			throw new RuntimeException('Nullable requires a Checkbox field.');
		}

		$field->nullable = true;
	}

	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
