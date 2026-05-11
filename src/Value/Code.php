<?php

declare(strict_types=1);

namespace Celemas\Cms\Value;

use Celemas\Cms\Field\Capability\SyntaxAware;
use Celemas\Cms\Field\Capability\Translatable;
use Celemas\Cms\Field\Field;

/**
 * @property-read Field&Translatable&SyntaxAware $field
 */
class Code extends Text
{
	public function syntax(): string
	{
		$syntax = $this->data['syntax'] ?? null;

		if (is_string($syntax) && $syntax !== '') {
			return $syntax;
		}

		return $this->field->getDefaultSyntax();
	}
}
