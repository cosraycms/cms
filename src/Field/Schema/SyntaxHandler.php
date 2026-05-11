<?php

declare(strict_types=1);

namespace Celemas\Cms\Field\Schema;

use Celemas\Cms\Exception\RuntimeException;
use Celemas\Cms\Field\Capability\SyntaxAware;
use Celemas\Cms\Field\Field;

class SyntaxHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof SyntaxAware) {
			$field->syntaxes($meta->syntaxes);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, SyntaxAware::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof SyntaxAware) {
			return ['syntaxes' => $field->getSyntaxes()];
		}

		return [];
	}
}
