<?php

declare(strict_types=1);

namespace Cosray\Richtext;

/** The rules for what a stored link may point at. */
final class Link
{
	private const array SCHEMES = ['http', 'https', 'mailto', 'tel'];

	/** Whether an address is relative or uses a scheme that cannot run code in the page. */
	public static function safe(string $href): bool
	{
		// Browsers ignore spaces and control characters around a URL, and tabs and newlines inside it.
		$url = (string) preg_replace('/[\x00-\x20]+/', '', $href);

		if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $match) !== 1) {
			return true;
		}

		return in_array(strtolower($match[1]), self::SCHEMES, true);
	}
}
