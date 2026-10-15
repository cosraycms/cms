<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\DefaultValue;

class DefaultValueHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof DefaultValue, 'The registry maps #[DefaultValue] to this handler');

		$default = $meta->default;
		$field->default(is_callable($default) ? $default() : $default);
	}

	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
