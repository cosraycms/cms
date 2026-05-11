<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Grid\Resizable;
use Celemas\Cms\Field\Field;

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
