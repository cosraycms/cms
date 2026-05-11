<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Searchable;
use Celemas\Cms\Field\Field;

class FulltextHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Searchable) {
			$field->fulltext($meta->fulltextWeight);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Searchable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
