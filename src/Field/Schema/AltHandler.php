<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Field;
use Cosray\Field\Image;
use Cosray\Schema\Alt;
use Override;

class AltHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Alt, 'The registry maps #[Alt] to this handler');

		if (!$field instanceof Image) {
			throw new RuntimeException($this->capabilityErrorMessage($field, Image::class));
		}

		if ($meta->required) {
			$field->requireItem('alt');
		}
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return [];
	}
}
