<?php

declare(strict_types=1);

namespace Cosray\Assets;

/**
 * The colour profile segments of a JPEG file. A photo in Adobe RGB or Display P3
 * only looks right with its profile: without it a browser takes the numbers for
 * sRGB and shows muted colours.
 */
final class JpegProfile
{
	private const string SIGNATURE = "ICC_PROFILE\0";
	private const int APP2 = 0xE2;
	private const int START_OF_SCAN = 0xDA;
	private const int END_OF_IMAGE = 0xD9;

	/**
	 * The segments as they stand in the file; empty without a profile or with an
	 * sRGB one, which only repeats what a browser assumes.
	 */
	public static function read(string $file): string
	{
		$handle = fopen($file, 'rb');

		if ($handle === false || fread($handle, 2) !== "\xFF\xD8") {
			return '';
		}

		$segments = '';

		try {
			while (true) {
				$head = fread($handle, 4);

				if ($head === false || strlen($head) < 4 || $head[0] !== "\xFF") {
					break;
				}

				$marker = ord($head[1]);

				if ($marker === self::START_OF_SCAN || $marker === self::END_OF_IMAGE) {
					break;
				}

				$length = (ord($head[2]) << 8) | ord($head[3]);

				if ($length < 2) {
					break;
				}

				$body = $length > 2 ? fread($handle, $length - 2) : '';

				if ($marker === self::APP2 && str_starts_with((string) $body, self::SIGNATURE)) {
					$segments .= $head . $body;
				}
			}
		} finally {
			fclose($handle);
		}

		return self::isSrgb($segments) ? '' : $segments;
	}

	/** Puts segments into a JPEG file behind its start and a JFIF header, as the format asks. */
	public static function insert(string $file, string $segments): void
	{
		if ($segments === '') {
			return;
		}

		$jpeg = (string) file_get_contents($file);
		$at = 2;

		if (substr($jpeg, 2, 2) === "\xFF\xE0") {
			$at += 2 + ((ord($jpeg[4]) << 8) | ord($jpeg[5]));
		}

		file_put_contents($file, substr($jpeg, 0, $at) . $segments . substr($jpeg, $at));
	}

	/** A profile names itself in its description, in UTF-16 from version 4 on. */
	private static function isSrgb(string $segments): bool
	{
		return str_contains(str_replace("\0", '', substr($segments, 0, 65536)), 'sRGB');
	}
}
