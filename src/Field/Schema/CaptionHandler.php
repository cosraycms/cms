<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Captionable;
use Cosray\Field\Field;
use Override;

class CaptionHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Captionable) {
			$field->caption();

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
