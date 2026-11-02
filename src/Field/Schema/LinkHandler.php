<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Linkable;
use Cosray\Field\Field;
use Override;

class LinkHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		if ($field instanceof Linkable) {
			$field->linkable();

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Linkable::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return $field instanceof Linkable ? ['link' => $field->isLinkable()] : [];
	}
}
