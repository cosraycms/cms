<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Blocks\Resizable;
use Cosray\Field\Field;

class ColumnsHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Resizable) {
			$field->columns($meta->columns, $meta->minCellWidth);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Resizable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Resizable) {
			return [
				'columns' => $field->getColumns(),
				'minCellWidth' => $field->getMinCellWidth(),
			];
		}

		return [];
	}
}
