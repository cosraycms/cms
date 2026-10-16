<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Entries;
use Cosray\Field\Field;
use Cosray\Schema\Allows;
use Override;

class AllowsHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		if (($field instanceof Entries || $field instanceof Blocks) && $meta instanceof Allows) {
			$field->allow(...$meta->types);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Entries::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
