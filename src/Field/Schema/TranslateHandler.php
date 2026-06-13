<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Translatable;
use Cosray\Field\Field;

class TranslateHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Translatable) {
			$field->translate($meta->mode);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Translatable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Translatable) {
			return ['translate' => $field->isTranslatable()];
		}

		return [];
	}
}
