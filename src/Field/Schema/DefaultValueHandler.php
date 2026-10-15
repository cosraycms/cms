<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;

class DefaultValueHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		$default = $meta->default;
		$field->default(is_callable($default) ? $default() : $default);
	}

	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
