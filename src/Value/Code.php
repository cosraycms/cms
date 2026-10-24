<?php

declare(strict_types=1);

namespace Cosray\Value;

use Cosray\Field\Capability\SyntaxAware;
use Cosray\Field\Capability\Translatable;
use Cosray\Field\Field;
use Override;

/**
 * @property-read Field&Translatable&SyntaxAware $field
 */
class Code extends Text
{
	/** @return array{code: string, syntax: string} */
	#[Override]
	public function json(): array
	{
		return ['code' => $this->raw(), 'syntax' => $this->syntax()];
	}

	public function syntax(): string
	{
		$syntax = $this->meta('syntax');

		if (is_string($syntax) && $syntax !== '') {
			return $syntax;
		}

		return $this->field->getDefaultSyntax();
	}
}
