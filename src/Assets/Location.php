<?php

declare(strict_types=1);

namespace Cosray\Assets;

/**
 * Removes where a photo was taken from the metadata of an image file: the GPS
 * block of EXIF and the GPS properties of XMP. Originals are public, and a phone
 * writes these unasked. Everything else stays, like the orientation and the
 * colour profile. Each file keeps its length, as its offsets depend on it.
 *
 * Place names an editor or a program wrote into text fields stay.
 */
final class Location
{
	private const int GPS_POINTER = 0x8825;
	private const array TIFF_HEADERS = ["II*\0", "MM\0*"];

	/** Bytes per value of the TIFF field types, by type. */
	private const array TYPE_SIZES = [
		1 => 1,
		2 => 1,
		3 => 2,
		4 => 4,
		5 => 8,
		6 => 1,
		7 => 1,
		8 => 2,
		9 => 4,
		10 => 8,
		11 => 4,
		12 => 8,
	];

	private const string XMP_ELEMENT = '~<([\w.-]+:gps\w*)\b[^>]*>.*?</\1>~is';
	private const string XMP_ATTRIBUTE = '~\b[\w.-]+:gps\w*\s*=\s*("[^"]*"|\'[^\']*\')~is';

	public static function removed(string $contents, string $mime): string
	{
		return match ($mime) {
			'image/png' => self::png($contents),
			'image/jpeg', 'image/webp', 'image/avif' => self::xmp(self::everyTiff($contents)),
			default => $contents,
		};
	}

	/** The EXIF of these formats sits in a segment, a chunk or a box without a checksum. */
	private static function everyTiff(string $contents): string
	{
		foreach (self::TIFF_HEADERS as $header) {
			$at = strpos($contents, $header);

			while ($at !== false) {
				$contents = self::tiff($contents, $at);
				$at = strpos($contents, $header, $at + 4);
			}
		}

		return $contents;
	}

	/** Takes the pointer to the GPS block out of the first directory and blanks the block. */
	private static function tiff(string $data, int $start): string
	{
		$little = $data[$start] === 'I';
		$first = self::number($data, $start + 4, 4, $little);

		if ($first === null) {
			return $data;
		}

		$directory = $start + $first;
		$count = self::number($data, $directory, 2, $little);

		if ($count === null || $count === 0 || ($directory + 2 + ($count * 12) + 4) > strlen($data)) {
			return $data;
		}

		for ($i = 0; $i < $count; $i++) {
			$entry = $directory + 2 + ($i * 12);

			if (self::number($data, $entry, 2, $little) !== self::GPS_POINTER) {
				continue;
			}

			$block = self::number($data, $entry + 8, 4, $little);
			$data = $block === null ? $data : self::blankBlock($data, $start, $start + $block, $little);
			$tail = $directory + 2 + ($count * 12) + 4 - ($entry + 12);
			$data = substr_replace($data, substr($data, $entry + 12, $tail) . str_repeat("\0", 12), $entry, $tail + 12);

			return substr_replace($data, pack($little ? 'v' : 'n', $count - 1), $directory, 2);
		}

		return $data;
	}

	private static function blankBlock(string $data, int $start, int $block, bool $little): string
	{
		$count = self::number($data, $block, 2, $little);

		if ($count === null || ($block + 2 + ($count * 12) + 4) > strlen($data)) {
			return $data;
		}

		for ($i = 0; $i < $count; $i++) {
			$entry = $block + 2 + ($i * 12);
			$size =
				(self::TYPE_SIZES[self::number($data, $entry + 2, 2, $little) ?? 0] ?? 0)
				* (self::number($data, $entry + 4, 4, $little) ?? 0);
			$value = self::number($data, $entry + 8, 4, $little);

			if ($size > 4 && $value !== null && ($start + $value + $size) <= strlen($data)) {
				$data = substr_replace($data, str_repeat("\0", $size), $start + $value, $size);
			}
		}

		$length = 2 + ($count * 12) + 4;

		return substr_replace($data, str_repeat("\0", $length), $block, $length);
	}

	private static function number(string $data, int $at, int $bytes, bool $little): ?int
	{
		if ($at < 0 || ($at + $bytes) > strlen($data)) {
			return null;
		}

		$format = match (true) {
			$bytes === 2 && $little => 'v',
			$bytes === 2 => 'n',
			$little => 'V',
			default => 'N',
		};
		$value = unpack($format, substr($data, $at, $bytes));

		return $value === false ? null : (int) $value[1];
	}

	/** XMP is text, so spaces take the place of what goes. */
	private static function xmp(string $text): string
	{
		if (stripos($text, ':gps') === false) {
			return $text;
		}

		foreach ([self::XMP_ELEMENT, self::XMP_ATTRIBUTE] as $pattern) {
			$text =
				preg_replace_callback(
					$pattern,
					static fn(array $match): string => str_repeat(' ', strlen($match[0])),
					$text,
				) ?? $text;
		}

		return $text;
	}

	/** A PNG keeps these in chunks of their own, each with a checksum to follow. */
	private static function png(string $data): string
	{
		$at = 8;

		while (($at + 12) <= strlen($data)) {
			$length = self::number($data, $at, 4, false) ?? 0;
			$type = substr($data, $at + 4, 4);

			if (($at + 12 + $length) > strlen($data)) {
				break;
			}

			if ($type === 'eXIf' || $type === 'iTXt') {
				$body = substr($data, $at + 8, $length);
				$clean = $type === 'eXIf' ? self::tiff($body, 0) : self::xmp($body);

				if ($clean !== $body) {
					$data = substr_replace($data, $clean . pack('N', crc32($type . $clean)), $at + 8, $length + 4);
				}
			}

			$at += 12 + $length;
		}

		return $data;
	}
}
