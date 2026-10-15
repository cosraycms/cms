<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;

class WidthHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		$field->width($meta->width);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['width' => $field->getWidth()];
	}
}
