// Defines <cosray-link>: the editor of a Link field. Each edited language
// holds one target, chosen in the link dialog richtext and images use. An
// empty translated language shows the link it falls back to.

/** @import { AssetInfo, AssetMap, LocaleMap, StoredLink } from '../types/data' */
/** @import { ControlLocales } from '../lib/control.js' */
/** @import { LibraryItem } from '../lib/library-browser.js' */

import { ZXX } from '../lib/content.js';
import { editedLocale, fallbackLabel, reportChange } from '../lib/control.js';
import { resolveFallback } from '../lib/fallback.js';
import { create } from './media/views.js';
import { linkRow, linkText } from './link/row.js';

/**
 * @typedef {object} LinkField
 * @property {string} [label]
 * @property {boolean} [immutable]
 * @property {boolean} [translate]
 */

/**
 * @param {unknown} link
 * @returns {boolean}
 */
function hasTarget(link) {
	return (
		typeof link === 'object' &&
		link !== null &&
		['href', 'node', 'asset'].some((key) => {
			const target = /** @type {Record<string, unknown>} */ (link)[key];

			return typeof target === 'string' && target !== '';
		})
	);
}

export class CosrayLink extends HTMLElement {
	/** @type {LinkField} */
	field = {};
	node = '';
	/** @type {ControlLocales | undefined} */
	locales;
	/** @type {AssetMap} */
	assets = {};

	/** @type {LocaleMap<StoredLink | null>} */
	#value = {};
	#locale = ZXX;
	#rendered = false;
	/** @type {Record<string, AssetInfo>} */
	#picked = {};
	#root = create('div', 'cms-link');

	get value() {
		return this.#value;
	}

	/** @param {LocaleMap<StoredLink | null> | null | undefined} value */
	set value(value) {
		this.#value = value && typeof value === 'object' ? { ...value } : {};

		if (this.#rendered) {
			this.#render();
		}
	}

	get locale() {
		return this.#locale;
	}

	// The content-language behavior switches the edited locale through the host.
	set locale(locale) {
		const previous = editedLocale(this.field, this.#locale);
		this.#locale = locale;

		if (this.#rendered && editedLocale(this.field, locale) !== previous) {
			this.#render();
		}
	}

	connectedCallback() {
		if (this.#rendered) {
			return;
		}

		this.#rendered = true;
		this.#root.setAttribute('role', 'group');

		if (this.field.label) {
			this.#root.setAttribute('aria-label', this.field.label);
		}

		this.append(this.#root);
		this.#render();
	}

	/** @param {string} uid */
	#asset(uid) {
		return this.#picked[uid] ?? this.assets?.[uid];
	}

	#render() {
		const edited = editedLocale(this.field, this.#locale);
		const current = this.#value[edited];
		const row = linkRow({
			owner: this,
			link: hasTarget(current) ? /** @type {StoredLink} */ (current) : undefined,
			asset: (uid) => this.#asset(uid),
			picked: (/** @type {LibraryItem} */ item) => {
				this.#picked[item.uid] = item;
			},
			readonly: this.field.immutable === true,
			change: (link) => this.#change(edited, link),
		});
		this.#root.replaceChildren(row.element);
		this.#showFallback(edited);
	}

	/** @param {string} edited */
	#showFallback(edited) {
		this.#root.querySelector(':scope > .fallback')?.remove();
		const all = this.locales?.all ?? [];
		const fallback =
			this.field.translate && !hasTarget(this.#value[edited])
				? resolveFallback(this.#value, edited, all, hasTarget)
				: null;

		if (fallback?.value) {
			const note = create('p', 'fallback', `${fallbackLabel(fallback, all)}:`);
			note.append(linkText(fallback.value, (uid) => this.#asset(uid)));
			this.#root.append(note);
		}
	}

	/**
	 * @param {string} locale
	 * @param {StoredLink | undefined} link
	 */
	#change(locale, link) {
		this.#value = { ...this.#value, [locale]: link ?? null };
		reportChange(this, { value: this.#value });
		this.#showFallback(locale);
	}
}

if (!customElements.get('cosray-link')) {
	customElements.define('cosray-link', CosrayLink);
}
