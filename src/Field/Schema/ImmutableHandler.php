<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Immutable;
use Celemas\Cms\Field\Field;

class ImmutableHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Immutable) {
			$field->immutable(true);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Immutable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Immutable) {
			return ['immutable' => $field->getImmutable()];
		}

		return [];
	}
}
