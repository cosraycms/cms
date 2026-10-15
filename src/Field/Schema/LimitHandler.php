<?php

declare(strict_types=1);

namespace Cosray\Field\Schema;

use Cosray\Exception\RuntimeException;
use Cosray\Field\Capability\Limitable;
use Cosray\Field\Field;
use Cosray\Schema\Limit;

class LimitHandler extends Handler
{
	public function apply(object $meta, Field $field): void
	{
		assert($meta instanceof Limit, 'The registry maps #[Limit] to this handler');

		if ($field instanceof Limitable) {
			$field->limit($meta->max, $meta->min);

			return;
		}

		throw new RuntimeException($this->capabilityErrorMessage($field, Limitable::class));
	}

	public function properties(object $meta, Field $field): array
	{
		if ($field instanceof Limitable) {
			return [
				'limit' => [
					'min' => $field->getLimitMin(),
					'max' => $field->getLimitMax(),
				],
			];
		}

		return [];
	}
}
