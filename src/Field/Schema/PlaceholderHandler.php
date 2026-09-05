<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Placeholdable;
use Cosray\Field\Field;

class PlaceholderHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Placeholdable) {
			$field->placeholder($meta->placeholder);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Placeholdable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Placeholdable) {
			return ['placeholder' => $field->getPlaceholder()];
		}

		return [];
	}
}
