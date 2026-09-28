<?php

declare(strict_types=1);

namespace Cosray\Value;

class Color extends Value
{
	/**
	 * Only a normalized `#rrggbb` ever leaves the value, so it is safe
	 * in a style attribute or custom property where HTML escaping is not
	 * enough; anything else stored reads as no colour.
	 */
	public function __toString(): string
	{
		return $this->unwrap();
	}

	public function unwrap(): string
	{
		return self::normalize($this->value()) ?? '';
	}

	public function json(): string
	{
		return $this->unwrap();
	}

	public function isset(): bool
	{
		return $this->unwrap() !== '';
	}

	/**
	 * Lowercase `#rrggbb` from a hex colour with three or six digits and
	 * an optional leading `#`, or null for anything else.
	 */
	public static function normalize(mixed $value): ?string
	{
		if (!is_string($value) || preg_match('/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($value), $match) !== 1) {
			return null;
		}

		$hex = strtolower($match[1]);

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return '#' . $hex;
	}
}
