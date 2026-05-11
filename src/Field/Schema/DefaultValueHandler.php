<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Defaultable;
use Celemas\Cms\Field\Field;

class DefaultValueHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Defaultable) {
			$default = $meta->default;
			$field->default(is_callable($default) ? $default() : $default);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Defaultable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
