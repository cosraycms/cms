<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Capability\SyntaxAware;
use Cosray\Field\Capability\Translatable;
use Cosray\Field\Field;

/**
 * @property-read Field&Translatable&SyntaxAware $field
 */
class Code extends Text
{
	public function syntax(): string
	{
		$syntax = $this->meta('syntax');

		if (is_string($syntax) && $syntax !== '') {
			return $syntax;
		}

		return $this->field->getDefaultSyntax();
	}
}
