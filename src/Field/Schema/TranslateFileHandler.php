<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\File\Translatable;
use Celemas\Cms\Field\Field;

class TranslateFileHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Translatable) {
			$field->translateFile(true);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Translatable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Translatable) {
			return ['translateFile' => $field->getTranslateFile()];
		}

		return [];
	}
}
