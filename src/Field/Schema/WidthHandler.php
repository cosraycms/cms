<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Resizable;
use Celemas\Cms\Field\Field;

class WidthHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Resizable) {
			$field->width($meta->width);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Resizable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Resizable) {
			return ['width' => $field->getWidth()];
		}

		return [];
	}
}
