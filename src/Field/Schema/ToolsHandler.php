<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\ToolsAware;
use Cosray\Field\Field;
use Cosray\Schema\Tools;
use Override;

class ToolsHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Tools, 'The registry maps #[Tools] to this handler');

		if ($field instanceof ToolsAware) {
			$field->tools(...$meta->tools);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, ToolsAware::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		// The field emits the resolved list itself, so fields without the
		// attribute carry their `tools` property too.
		return [];
	}
}
