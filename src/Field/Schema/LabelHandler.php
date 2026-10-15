<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\Label;

class LabelHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Label, 'The registry maps #[Label] to this handler');

		$field->label($meta->label);
	}

	public function properties(object $meta, Field $field): array
	{
		return ['label' => $field->getLabel()];
	}
}
