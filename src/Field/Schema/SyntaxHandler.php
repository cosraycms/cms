<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\SyntaxAware;
use Cosray\Field\Field;
use Cosray\Schema\Syntax;
use Override;

class SyntaxHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Syntax, 'The registry maps #[Syntax] to this handler');

		if ($field instanceof SyntaxAware) {
			$field->syntaxes($meta->syntaxes);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, SyntaxAware::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof SyntaxAware) {
			return ['syntaxes' => $field->getSyntaxes()];
		}

		return [];
	}
}
