<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Targetable;
use Cosray\Field\Field;
use Cosray\Schema\Targets;

class TargetsHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Targetable && $meta instanceof Targets) {
			$field->target(...$meta->types);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Targetable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
