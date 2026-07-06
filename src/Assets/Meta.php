<?php

declare(strict_types=1);

namespace Cosray\Assets;

/**
 * The editable slice of an asset's `meta` bag: localized text
 * (`alt`/`title`/`caption` as locale => string maps), a scalar `credit`
 * line, and an image `focal` point `{x, y}` clamped to 0..1. Empty
 * values are dropped so the stored bag never accumulates blank keys, and
 * keys the panel does not manage are carried through untouched.
 */
final class Meta
{
	/** Localized text keys, stored as locale => string maps. */
	private const array TEXT = ['alt', 'title', 'caption'];

	/**
	 * Merge a submitted editable patch over the stored bag. The managed
	 * keys are cleared first, so a field the user emptied is removed
	 * rather than left at its previous value.
	 *
	 * @param array<string, mixed> $stored the asset's current meta
	 * @param list<string> $localeIds locale ids accepted as text-map keys
	 * @return array<string, mixed>
	 */
	public static function apply(array $stored, mixed $input, array $localeIds, bool $image): array
	{
		foreach ([...self::TEXT, 'credit', 'focal'] as $key) {
			unset($stored[$key]);
		}

		$input = is_array($input) ? $input : [];

		foreach (self::TEXT as $key) {
			$map = self::textMap($input[$key] ?? null, $localeIds);

			if ($map !== []) {
				$stored[$key] = $map;
			}
		}

		$credit = is_string($input['credit'] ?? null) ? trim($input['credit']) : '';

		if ($credit !== '') {
			$stored['credit'] = $credit;
		}

		if ($image) {
			$focal = self::focal($input['focal'] ?? null);

			if ($focal !== null) {
				$stored['focal'] = $focal;
			}
		}

		return $stored;
	}

	/**
	 * @param list<string> $localeIds
	 * @return array<string, string>
	 */
	private static function textMap(mixed $value, array $localeIds): array
	{
		if (!is_array($value)) {
			return [];
		}

		$allowed = $localeIds === [] ? null : array_fill_keys($localeIds, true);
		$map = [];

		foreach ($value as $locale => $text) {
			if (!is_string($locale) || $allowed !== null && !isset($allowed[$locale])) {
				continue;
			}

			$text = is_string($text) ? trim($text) : '';

			if ($text !== '') {
				$map[$locale] = $text;
			}
		}

		return $map;
	}

	/** @return array{x: float, y: float}|null */
	private static function focal(mixed $value): ?array
	{
		if (
			!is_array($value)
			|| !isset($value['x'], $value['y'])
			|| !is_numeric($value['x'])
			|| !is_numeric($value['y'])
		) {
			return null;
		}

		return [
			'x' => self::clamp((float) $value['x']),
			'y' => self::clamp((float) $value['y']),
		];
	}

	private static function clamp(float $n): float
	{
		return max(0.0, min(1.0, $n));
	}
}
