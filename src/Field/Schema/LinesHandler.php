<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Multiline;
use Cosray\Field\Field;

class LinesHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Multiline) {
			$field->lines($meta->lines);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Multiline::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Multiline) {
			return ['lines' => $field->getLines()];
		}

		return [];
	}
}
