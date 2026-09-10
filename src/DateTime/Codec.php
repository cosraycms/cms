<?php

declare(strict_types=1);

namespace Cosray\DateTime;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

final class Codec
{
	public const string STORAGE_FORMAT = 'Y-m-d\\TH:i:s\\Z';
	public const string INPUT_FORMAT = 'Y-m-d\\TH:i:s';

	private const string RFC3339 = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/';

	public static function parse(string $value): ?DateTimeImmutable
	{
		if (preg_match(self::RFC3339, $value) !== 1) {
			return null;
		}

		$date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:sP', $value);

		if ($date === false || !self::clean()) {
			return null;
		}

		$expected = str_ends_with($value, 'Z') ? substr($value, 0, -1) . '+00:00' : $value;

		return $date->format('Y-m-d\\TH:i:sP') === $expected ? $date : null;
	}

	public static function normalize(string $value): ?string
	{
		$date = self::parse($value);

		return $date?->setTimezone(self::utc())->format(self::STORAGE_FORMAT);
	}

	public static function format(DateTimeInterface $value): string
	{
		return DateTimeImmutable::createFromInterface($value)
			->setTimezone(self::utc())
			->format(self::STORAGE_FORMAT);
	}

	public static function input(string $value, DateTimeZone $timezone): ?string
	{
		return self::parse($value)?->setTimezone($timezone)->format(self::INPUT_FORMAT);
	}

	public static function fromInput(string $value, DateTimeZone $timezone): ?string
	{
		if ($value === '') {
			return '';
		}

		$formats = strlen($value) === 16 ? ['!Y-m-d\\TH:i', 'Y-m-d\\TH:i'] : ['!Y-m-d\\TH:i:s', 'Y-m-d\\TH:i:s'];
		$date = DateTimeImmutable::createFromFormat($formats[0], $value, $timezone);

		if ($date === false || !self::clean() || $date->format($formats[1]) !== $value) {
			return null;
		}

		return self::format($date);
	}

	public static function fromLegacy(string $value, DateTimeZone $timezone): ?string
	{
		$normalized = self::normalize($value);

		if ($normalized !== null) {
			return $normalized;
		}

		if (str_contains($value, 'T')) {
			return self::fromInput($value, $timezone);
		}

		$date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);

		if ($date === false || !self::clean() || $date->format('Y-m-d H:i:s') !== $value) {
			return null;
		}

		return self::format($date);
	}

	public static function timezone(mixed $value): ?DateTimeZone
	{
		if (!is_string($value) || $value === '') {
			return null;
		}

		try {
			return new DateTimeZone($value);
		} catch (Throwable) {
			return null;
		}
	}

	public static function utc(): DateTimeZone
	{
		return new DateTimeZone('UTC');
	}

	private static function clean(): bool
	{
		$errors = DateTimeImmutable::getLastErrors();

		return $errors === false || $errors['warning_count'] === 0 && $errors['error_count'] === 0;
	}
}
