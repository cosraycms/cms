<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Selectable;
use Celemas\Cms\Field\Field;

class OptionsHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Selectable) {
			$field->options($meta->options);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Selectable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Selectable) {
			return ['options' => $field->getOptions()];
		}

		return [];
	}
}
