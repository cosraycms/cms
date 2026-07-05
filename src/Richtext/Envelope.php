<?php

declare(strict_types=1);

namespace Cosray\Richtext;

/**
 * The format envelope of stored richtext values. In node content the
 * envelope keys (`format`, `version`) live on the field or block
 * object next to `value`; a missing `format` marks legacy HTML from
 * before the structured-richtext migration.
 */
final class Envelope
{
	public const string FORMAT = 'cosray-richtext';
	public const string HTML = 'html';
	public const int VERSION = 1;

	/** @param array<string, mixed> $data A field or block data array. */
	public static function format(array $data): string
	{
		$format = $data['format'] ?? null;

		return is_string($format) && $format !== '' ? $format : self::HTML;
	}

	/** @param array<string, mixed> $data A field or block data array. */
	public static function isStructured(array $data): bool
	{
		return self::format($data) === self::FORMAT;
	}
}
