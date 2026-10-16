<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Blocks\Resizable;
use Cosray\Field\Field;
use Cosray\Schema\Columns;
use Override;

class ColumnsHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Columns, 'The registry maps #[Columns] to this handler');

		if ($field instanceof Resizable) {
			$field->columns($meta->columns, $meta->min, $meta->responsive);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Resizable::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Resizable) {
			return [
				'columns' => $field->getColumns(),
				'min' => $field->getMin(),
				'responsive' => $field->getResponsive()->value,
			];
		}

		return [];
	}
}
