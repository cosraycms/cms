<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\Translatable;
use Celemas\Cms\Field\Field;

class TranslateHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Translatable) {
			$field->translate(true);

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
