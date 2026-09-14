<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Checkbox;
use Cosray\Field\Field;
use Cosray\Schema\StateLabels;

class StateLabelsHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if (!$field instanceof Checkbox) {
			throw new RuntimeException('StateLabels requires a Checkbox field.');
		}

		assert($meta instanceof StateLabels);
		$field->stateLabels = $meta;
	}

	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
