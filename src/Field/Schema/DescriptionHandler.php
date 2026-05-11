<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Describable;
use Celemas\Cms\Field\Field;

class DescriptionHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Describable) {
			$field->description($meta->description);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Describable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Describable) {
			return ['description' => $field->getDescription()];
		}

		return [];
	}
}
