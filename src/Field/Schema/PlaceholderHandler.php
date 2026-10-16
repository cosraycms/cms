<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Placeholdable;
use Cosray\Field\Field;
use Cosray\Schema\Placeholder;
use Override;

class PlaceholderHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Placeholder, 'The registry maps #[Placeholder] to this handler');

		if ($field instanceof Placeholdable) {
			$field->placeholder($meta->placeholder);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Placeholdable::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Placeholdable) {
			return ['placeholder' => $field->getPlaceholder()];
		}

		return [];
	}
}
