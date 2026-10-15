<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;

class HiddenHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		$field->hidden(true);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['hidden' => $field->getHidden()];
	}
}
