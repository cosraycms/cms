<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Cosray\Assets\Asset;
use NumberFormatter;

/** How the panel states an asset's file facts: size, extension, dimensions. */
final class AssetFacts
{
	/** A byte count in the largest fitting unit, formatted for the locale. */
	public static function size(int $bytes, string $locale): string
	{
		$units = ['B', 'KB', 'MB', 'GB', 'TB'];
		$size = (float) max(0, $bytes);
		$unit = 0;

		while ($size >= 1024 && $unit < (count($units) - 1)) {
			$size /= 1024;
			$unit++;
		}

		$formatter = new NumberFormatter($locale, NumberFormatter::DECIMAL);
		$digits = $unit === 0 ? 0 : 1;
		$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $digits);
		$formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $digits);
		$value = $formatter->format($size);

		return ($value === false ? (string) $size : $value) . ' ' . $units[$unit];
	}

	/** Up to five characters of the filename's extension, upper case. */
	public static function extension(string $filename): string
	{
		$dot = strrpos($filename, '.');

		return $dot === false ? '' : strtoupper(substr($filename, $dot + 1, 5));
	}

	/**
	 * The one-line fact row under an asset name: pixel dimensions when
	 * known, otherwise the extension, then the size, as in
	 * "2400 × 1600 px · 842 KB".
	 */
	public static function line(Asset $asset, string $locale): string
	{
		$parts = [];

		if ($asset->width && $asset->height) {
			$parts[] = "{$asset->width} × {$asset->height} px";
		} elseif (self::extension($asset->filename) !== '') {
			$parts[] = self::extension($asset->filename);
		}

		if ($asset->bytes !== null) {
			$parts[] = self::size($asset->bytes, $locale);
		}

		return implode(' · ', $parts);
	}
}
