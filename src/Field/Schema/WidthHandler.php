<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\Width;

class WidthHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Width, 'The registry maps #[Width] to this handler');

		$field->width($meta->width);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['width' => $field->getWidth()];
	}
}
