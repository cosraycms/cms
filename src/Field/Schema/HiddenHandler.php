<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Override;

class HiddenHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		$field->hidden(true);
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return ['hidden' => $field->getHidden()];
	}
}
