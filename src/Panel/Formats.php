<?php

declare(strict_types=1);

namespace Cosray\Panel;

use Collator;
use DateTimeImmutable;
use DateTimeZone;
use IntlDateFormatter;
use Locale;
use NumberFormatter;
use ResourceBundle;

/** The regions and time zones the panel formats dates and numbers in. */
final class Formats
{
	/**
	 * The regions ICU has formats for in a language, named in the panel
	 * language and sorted by name. Other regions would format like the
	 * language's default.
	 *
	 * @return array<string, string> region code => name
	 */
	public static function regions(string $language, string $panelLanguage): array
	{
		$regions = [];

		foreach (ResourceBundle::getLocales('') ?: [] as $locale) {
			$region = (string) Locale::getRegion($locale);

			if (
				$region !== ''
				&& Locale::getPrimaryLanguage($locale) === $language
				&& Locale::getScript($locale) === ''
			) {
				$regions[$region] = Locale::getDisplayRegion("und_{$region}", $panelLanguage) ?: $region;
			}
		}

		new Collator($panelLanguage)->asort($regions);

		return $regions;
	}

	/** The region code, uppercased, when ICU knows it. */
	public static function region(?string $region): ?string
	{
		if ($region === null || preg_match('/^(?:[A-Za-z]{2}|\d{3})$/D', $region) !== 1) {
			return null;
		}

		$region = strtoupper($region);

		return Locale::getDisplayRegion("und_{$region}", 'en') !== $region ? $region : null;
	}

	/** @return list<string> */
	public static function timezones(): array
	{
		return DateTimeZone::listIdentifiers();
	}

	public static function timezone(?string $timezone): ?DateTimeZone
	{
		return $timezone !== null && in_array($timezone, self::timezones(), true) ? new DateTimeZone($timezone) : null;
	}

	/** The current time and a decimal number, as the panel shows them. */
	public static function example(string $locale, DateTimeZone $timezone): string
	{
		$date = new IntlDateFormatter($locale, IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, $timezone)->format(
			new DateTimeImmutable(),
		);
		$number = new NumberFormatter($locale, NumberFormatter::DECIMAL)->format(1234.5);

		return "{$date} · {$number}";
	}
}
