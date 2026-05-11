<?php

declare(strict_types=1);

namespace Celemas\Cms\Util;

class Strings
{
	/**
	 * Calculate entropy of a string.
	 */
	public static function entropy(string $str): float
	{
		$classes = [
			// lower case unicode characters
			'/\p{Ll}/',
			// upper case unicode characters
			'/\p{Lu}/',
			// unicode numbers
			'/\p{N}/',
		];

		$size = 0.0;
		$str = trim($str);
		$len = strlen($str);
		$classCount = 0;

		foreach ($classes as $pattern) {
			$matches = [];

			if (preg_match_all($pattern, $str, $matches)) {
				$size += count(array_unique($matches[0]));
				$classCount++;
			}
		}

		// special characters
		$matches = [];

		foreach (str_split("/[ ,.?!\"┬ú$%^&*()-_=+[]{};:'@#~<>/\\|`┬¼┬ª]/", 1) as $char) {
			if (!str_contains($str, $char)) {
				continue;
			}

			$matches[] = $char;
		}
		$foundSpecialChars = count(array_unique($matches));

		if ($foundSpecialChars > 0) {
			$classCount++;
			$size += $foundSpecialChars;
		}

		// Evaluate if its a simple string of chars next to each other
		//   Like: abcdef or 1234
		// This is only an approximation an should not add too much weight
		// If this is below certain thresholds
		$sumDiff = 1;
		$chars = str_split($str, 1);

		for ($i = 1; $i < count($chars); $i++) {
			$sumDiff += abs(mb_ord($chars[$i - 1]) - mb_ord($chars[$i]));
		}

		// probably something like acegik...
		if ($sumDiff <= 12) {
			$len--;
		}

		// probably something like 12345 or aaabbb
		if ($sumDiff <= 5) {
			$len--;
		}

		if ($classCount > 0) {
			$size += $classCount - 1;
		}

		if ($size === 0.0 || $len <= 0) {
			return 0;
		}

		return log($size, 2) * $len;
	}
}
