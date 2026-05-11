<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Validatable;
use Celemas\Cms\Field\Field;

class ValidateHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Validatable) {
			$field->addValidators(...$meta->validators);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Validatable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Validatable) {
			return ['validators' => $field->validators()];
		}

		return [];
	}
}
