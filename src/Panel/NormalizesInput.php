<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Traversable;

trait NormalizesInput
{
	private static function label(?string $label): ?string
	{
		$label = trim((string) $label);

		return $label === '' ? null : $label;
	}

	/** @return list<mixed> */
	private static function items(iterable $items): array
	{
		if ($items instanceof Traversable) {
			return array_values(iterator_to_array($items, false));
		}

		return array_values($items);
	}

	/** @return array<array-key, mixed> */
	private static function arrayFrom(mixed $value): array
	{
		if ($value instanceof Traversable) {
			return iterator_to_array($value);
		}

		return is_array($value) ? $value : [];
	}
}
