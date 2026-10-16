<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\Width;
use Override;

class WidthHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Width, 'The registry maps #[Width] to this handler');

		$field->width($meta->width);
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return ['width' => $field->getWidth()];
	}
}
