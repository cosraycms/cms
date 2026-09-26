/** @import { LocaleMap } from '../types/data' */

import { ZXX } from './content.js';

/**
 * @typedef {object} FallbackLocale
 * @property {string} id
 * @property {string} title
 * @property {string | null} [fallback]
 */

/**
 * @template T
 * @typedef {object} ResolvedFallback
 * @property {T} value
 * @property {string} locale
 */

/**
 * @param {unknown} value
 * @returns {boolean}
 */
export function filled(value) {
	return (
		value !== null &&
		value !== undefined &&
		value !== '' &&
		(!Array.isArray(value) || value.length > 0)
	);
}

/**
 * @template T
 * @param {LocaleMap<T> | undefined} map
 * @param {string} locale
 * @param {FallbackLocale[]} locales
 * @param {(value: T | undefined) => boolean} [hasValue]
 * @returns {ResolvedFallback<T> | null}
 */
export function resolveFallback(map, locale, locales, hasValue = filled) {
	if (!map) {
		return null;
	}

	const byId = new Map(locales.map((entry) => [entry.id, entry]));
	const seen = new Set([locale]);
	let next = byId.get(locale)?.fallback ?? null;

	while (next && !seen.has(next)) {
		seen.add(next);

		if (hasValue(map[next])) {
			return { value: map[next], locale: next };
		}

		next = byId.get(next)?.fallback ?? null;
	}

	return hasValue(map[ZXX]) ? { value: map[ZXX], locale: ZXX } : null;
}

/**
 * A per-use text with the catalog behind it. The catalog may be read in a
 * locale of its own: a neutral value is stored under `zxx`, which no
 * catalog map carries, while the site resolves the catalog in the page's
 * locale.
 *
 * @param {LocaleMap<string> | undefined} override
 * @param {LocaleMap<string> | undefined} catalog
 * @param {string} locale
 * @param {FallbackLocale[]} locales
 * @param {string} [catalogLocale]
 * @returns {ResolvedFallback<string> | null}
 */
export function resolveTextFallback(override, catalog, locale, locales, catalogLocale = locale) {
	if (filled(override?.[locale])) {
		return null;
	}

	const perUse = resolveFallback(override, locale, locales);

	if (perUse) {
		return perUse;
	}

	const current = catalog?.[catalogLocale];

	if (filled(current)) {
		return { value: current ?? '', locale: catalogLocale };
	}

	return resolveFallback(catalog, catalogLocale, locales);
}

/**
 * @param {FallbackLocale[]} locales
 * @param {string} locale
 * @returns {string}
 */
export function localeTitle(locales, locale) {
	return locales.find((entry) => entry.id === locale)?.title ?? locale.toUpperCase();
}
