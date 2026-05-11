<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Labelable;
use Celemas\Cms\Field\Field;

class LabelHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Labelable) {
			$field->label($meta->label);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Labelable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Labelable) {
			return ['label' => $field->getLabel()];
		}

		return [];
	}
}
