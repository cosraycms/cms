<?php

declare(strict_types=1);

namespace Cosray\Util;

use Exception;

class Number
{
	/**
	 * Parses a number string to a computer processible string
	 * which works with floatval.
	 *
	 * This works for any kind of input, American or European style.
	 */
	public static function parseDecimal(string $value): string
	{
		$value = preg_replace('/\s/', '', $value);

		if (preg_match('/^[0-9.,]+$/', $value)) {
			$value = str_replace(',', '.', $value);

			// remove all dots but the last one
			return preg_replace('/\.(?=.*\.)/', '', $value);
		}

		throw new Exception('This is not a valid number');
	}
}
