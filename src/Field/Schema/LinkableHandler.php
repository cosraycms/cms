<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability;
use Cosray\Field\Field;
use Cosray\Schema\Linkable;
use Override;

class LinkableHandler extends Handler
{
	#[Override]
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Linkable, 'The registry maps #[Linkable] to this handler');

		if ($field instanceof Capability\Linkable) {
			$field->linkable();

			if ($meta->required) {
				$field->requireItem('link');
			}

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Capability\Linkable::class));
	}

	#[Override]
	public function properties(object $meta, Field $field): array
	{
		return $field instanceof Capability\Linkable ? ['link' => $field->isLinkable()] : [];
	}
}
