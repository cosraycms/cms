// Shared parts of cosray's element controls. The host assigns the element
// contract (docs/controls.md) and mirrors reported edits into the form.

/** @import { FallbackLocale, ResolvedFallback } from './fallback.js' */

import { ZXX } from './content.js';
import { localeTitle } from './fallback.js';
import { __ } from './locale.js';

/**
 * @typedef {object} ControlLocales
 * @property {string} default
 * @property {FallbackLocale[]} all
 */

/**
 * The locale an element edits: the content language of a translated
 * field, the neutral locale otherwise.
 *
 * @param {{ translate?: boolean }} field
 * @param {string} locale
 * @returns {string}
 */
export function editedLocale(field, locale) {
	return field.translate ? locale : ZXX;
}

/**
 * The badge naming where a shown fallback comes from.
 *
 * @param {ResolvedFallback<unknown>} fallback
 * @param {FallbackLocale[]} locales
 * @returns {string}
 */
export function fallbackLabel(fallback, locales) {
	return fallback.locale === ZXX
		? __('field:fallback-shared')
		: __('field:fallback-from', { language: localeTitle(locales, fallback.locale) });
}

/**
 * Reports an edit to the host, which turns it into the submitted value.
 *
 * @param {HTMLElement} element
 * @param {{ value: unknown, meta?: unknown, format?: string, version?: number }} detail
 */
export function reportChange(element, detail) {
	element.dispatchEvent(
		new CustomEvent('cosray-change', { detail, bubbles: true, composed: true }),
	);
}
