<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Blocks;
use Cosray\Field\Field;
use Cosray\Schema\Common;
use Override;

final class CommonHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Blocks && $meta instanceof Common) {
			$field->common(...$meta->types);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Blocks::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
