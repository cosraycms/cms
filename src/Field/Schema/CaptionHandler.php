<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Captionable;
use Cosray\Field\Field;
use Cosray\Schema\Caption;
use Override;

class CaptionHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Caption, 'The registry maps #[Caption] to this handler');

		if ($field instanceof Captionable) {
			$field->caption();

			if ($meta->required) {
				$field->requireItem('caption');
			}

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Captionable::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return $field instanceof Captionable ? ['caption' => $field->hasCaption()] : [];
	}
}
