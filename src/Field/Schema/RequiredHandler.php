<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Requirable;
use Celemas\Cms\Field\Field;

class RequiredHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Requirable) {
			$field->required(true);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Requirable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Requirable) {
			return ['required' => $field->isRequired()];
		}

		return [];
	}
}
