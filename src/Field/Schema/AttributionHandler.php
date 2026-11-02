<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Attributable;
use Cosray\Field\Field;
use Cosray\Schema\Attribution;
use Override;

class AttributionHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Attribution, 'The registry maps #[Attribution] to this handler');

		if ($field instanceof Attributable) {
			$field->attribution();

			if ($meta->required) {
				$field->requireItem('attribution');
			}

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Attributable::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return $field instanceof Attributable ? ['attribution' => $field->hasAttribution()] : [];
	}
}
