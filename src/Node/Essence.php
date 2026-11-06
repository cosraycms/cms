<?php

declare(strict_types=1);

namespace Cosray\Node;

/**
 * Content as far as an editor can tell it apart. The form submits every
 * slot it shows, numbers as text, so empty values and fields holding
 * nothing but empty values count as absent, and 1041, 1041.0 and '1041'
 * are the same; key order does not count either.
 */
final class Essence
{
	public static function of(mixed $value): mixed
	{
		if (is_int($value) || is_float($value)) {
			return (string) $value;
		}

		if ($value === '') {
			return null;
		}

		if (!is_array($value)) {
			return $value;
		}

		if (array_is_list($value)) {
			return $value === [] ? null : array_map(self::of(...), $value);
		}

		$essence = array_filter(
			array_map(self::of(...), $value),
			static fn(mixed $item): bool => $item !== null,
		);

		$field = array_key_exists('type', $value) && array_key_exists('value', $value);

		if ($field && !isset($essence['value']) && !isset($essence['meta'])) {
			return null;
		}

		ksort($essence);

		return $essence === [] ? null : $essence;
	}

	public static function same(mixed $a, mixed $b): bool
	{
		return self::of($a) === self::of($b);
	}
}
