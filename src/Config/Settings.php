<?php

declare(strict_types=1);

namespace Celemas\Cms\Config;

final class Settings
{
	/**
	 * @param array<string, mixed> $base
	 * @param array<string, mixed> $override
	 * @return array<string, mixed>
	 */
	public static function merge(array $base, array $override): array
	{
		foreach ($override as $key => $value) {
			$current = $base[$key] ?? null;

			if (is_array($current) && is_array($value) && self::canMerge($current, $value)) {
				$base[$key] = self::merge($current, $value);
			} else {
				$base[$key] = $value;
			}
		}

		return $base;
	}

	/**
	 * @param array<array-key, mixed> $current
	 * @param array<array-key, mixed> $value
	 */
	private static function canMerge(array $current, array $value): bool
	{
		return !array_is_list($current) && !array_is_list($value);
	}
}
