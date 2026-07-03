<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\When;

class WhenHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		// Activation happens at hydration time (FieldHydrator); the
		// handler only carries the condition into the field payload.
	}

	public function properties(object $meta, Field $field): array
	{
		assert($meta instanceof When);

		return ['when' => $meta->condition()];
	}
}
