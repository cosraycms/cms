<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\ToolsAware;
use Cosray\Field\Field;

class ToolsHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof ToolsAware) {
			$field->tools(...$meta->tools);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, ToolsAware::class));
	}

	public function properties(object $meta, Field $field): array
	{
		// The field emits the resolved list itself, so fields without the
		// attribute carry their `tools` property too.
		return [];
	}
}
