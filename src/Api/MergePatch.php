<?php

declare(strict_types=1);

namespace Cosray\Api;

/**
 * JSON Merge Patch (RFC 7396): objects merge member by member, null removes
 * a member, and anything else, lists included, replaces the target.
 */
final class MergePatch
{
	public static function apply(mixed $target, mixed $patch): mixed
	{
		if (!self::isObject($patch)) {
			return $patch;
		}

		$result = self::isObject($target) ? $target : [];

		foreach ($patch as $key => $value) {
			if ($value === null) {
				unset($result[$key]);

				continue;
			}

			$result[$key] = self::apply($result[$key] ?? null, $value);
		}

		return $result;
	}

	/** @phpstan-assert-if-true array<array-key, mixed> $value */
	private static function isObject(mixed $value): bool
	{
		return is_array($value) && ($value === [] || !array_is_list($value));
	}
}
