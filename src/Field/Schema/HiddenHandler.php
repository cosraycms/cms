<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Hidable;
use Celemas\Cms\Field\Field;

class HiddenHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Hidable) {
			$field->hidden(true);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Hidable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Hidable) {
			return ['hidden' => $field->getHidden()];
		}

		return [];
	}
}
