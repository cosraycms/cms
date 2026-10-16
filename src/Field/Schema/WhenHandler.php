<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Field\Field;
use Cosray\Schema\When;
use Override;

class WhenHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		// Activation happens at hydration time (FieldHydrator); the
		// handler only carries the condition into the field payload.
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		assert($meta instanceof When, 'The registry only routes When attributes to this handler');

		return ['when' => $meta->condition()];
	}
}
