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

/**
 * The ARIA state of the part that carries the field's name: described by
 * the field's help text and the editor's error messages, invalid while
 * there are any.
 *
 * @param {{ descriptionId?: string }} field
 * @param {readonly string[]} errorIds
 * @returns {{ 'aria-describedby'?: string, 'aria-invalid'?: string }}
 */
export function controlAria(field, errorIds) {
	const ids = [field.descriptionId ?? '', ...errorIds].filter((id) => id !== '');

	return {
		...(ids.length > 0 ? { 'aria-describedby': ids.join(' ') } : {}),
		...(errorIds.length > 0 ? { 'aria-invalid': 'true' } : {}),
	};
}

/**
 * @param {Element} element
 * @param {{ descriptionId?: string }} field
 * @param {readonly string[]} errorIds
 */
export function applyControlAria(element, field, errorIds) {
	const aria = controlAria(field, errorIds);

	for (const name of /** @type {const} */ (['aria-describedby', 'aria-invalid'])) {
		const value = aria[name];

		if (value === undefined) {
			element.removeAttribute(name);
		} else {
			element.setAttribute(name, value);
		}
	}
}
