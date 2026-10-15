<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;

class RowspanHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		$field->rowspan($meta->rowspan);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['rowspan' => $field->getRowspan()];
	}
}
