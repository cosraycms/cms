<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Filterable;
use Cosray\Field\Field;
use Cosray\Schema\Filter;

class FilterHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Filterable && $meta instanceof Filter) {
			$field->filter($meta->query);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Filterable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
