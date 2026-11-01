// The per-use texts of a media item: an image's alt text, caption and
// attribution, a video's caption, a file's title, as far as the field offers
// them. An empty text falls back to the item's other languages and then to the
// asset's catalog, shown as placeholder. The attribution is one neutral string.

/** @import { FileItem, ItemMeta, LocaleMap, UploadType } from '../../types/data' */
/** @import { ControlLocales } from '../../lib/control.js' */

import { ZXX } from '../../lib/content.js';
import { fallbackLabel } from '../../lib/control.js';
import { resolveTextFallback } from '../../lib/fallback.js';
import { __ } from '../../lib/locale.js';

/** @typedef {'alt' | 'caption' | 'title' | 'attribution'} Key */

let ids = 0;

/**
 * @typedef {object} MetaFieldsOptions
 * @property {FileItem} item
 * @property {UploadType} kind
 * @property {Key[]} texts The texts the field offers, in order.
 * @property {boolean} translate
 * @property {string} contentLocale
 * @property {ControlLocales | undefined} locales
 * @property {ItemMeta | undefined} catalog The asset's catalog meta.
 * @property {(texts: ItemMeta) => void} update Receives the offered texts on every edit, for the
 *     owner to merge with `patchItemMeta`, which prunes the empty ones so they never shadow the
 *     catalog defaults.
 * @property {boolean} [readonly]
 */

/**
 * @param {MetaFieldsOptions} options
 * @returns {{ element: HTMLElement, setLocale: (locale: string) => void }}
 */
export function metaFields({
	item,
	kind,
	texts: keys,
	translate,
	contentLocale,
	locales,
	catalog,
	update,
	readonly = false,
}) {
	const id = `cms-media-meta-${++ids}`;
	const labels = {
		alt: __('image:alt-text'),
		caption: __('image:caption'),
		title: __('common:title'),
		attribution: __('image:attribution'),
	};
	const notes = {
		alt: __('image:alt-text-note'),
		caption: __('field:optional'),
		title: __('field:optional'),
		attribution: __('field:optional'),
	};
	/** @type {Record<string, LocaleMap<string>>} */
	const texts = Object.fromEntries(
		keys
			.filter((name) => name !== 'attribution')
			.map((name) => [name, { ...(item.meta?.[name] ?? {}) }]),
	);
	let attribution = item.meta?.attribution ?? '';
	/** @type {Key | null} */
	let focused = null;
	let locale = contentLocale;
	const all = locales?.all ?? [];
	const element = document.createElement('div');
	element.className = 'cms-media-meta';
	/** @type {Map<Key, { input: HTMLInputElement | HTMLTextAreaElement, entry: HTMLElement, note: HTMLElement }>} */
	const parts = new Map();

	const key = () => (translate ? locale : ZXX);
	// A neutral value reads its catalog text in the site's default locale, the
	// nearest the panel has to the page locale the site resolves with.
	const catalogLocale = () => (translate ? locale : (locales?.default ?? locale));

	/** @param {Key} name */
	function own(name) {
		return name === 'attribution' ? attribution : (texts[name][key()] ?? '');
	}

	/**
	 * @param {Key} name
	 * @returns {{ value: string, note: string } | null}
	 */
	function fallback(name) {
		if (focused === name) return null;

		if (name === 'attribution') {
			return !attribution && catalog?.attribution
				? { value: catalog.attribution, note: __('image:from-library') }
				: null;
		}

		const resolved = resolveTextFallback(
			texts[name],
			/** @type {LocaleMap<string> | undefined} */ (catalog?.[name]),
			key(),
			all,
			catalogLocale(),
		);

		return resolved && { value: resolved.value, note: fallbackLabel(resolved, all) };
	}

	/** @param {Key} name */
	function placeholder(name) {
		const resolved = fallback(name);

		if (resolved) return resolved.value;
		if (name === 'alt') return __('image:alt-text-placeholder');
		if (name === 'title') return __('media:title-placeholder');
		if (name === 'attribution') return __('image:attribution-placeholder');

		return kind === 'video' ? __('video:caption-placeholder') : __('image:caption-placeholder');
	}

	/** @param {Key} name */
	function refresh(name) {
		const part = parts.get(name);

		if (!part) return;

		part.input.placeholder = placeholder(name);
		const resolved = fallback(name);

		if (resolved) {
			part.note.textContent = resolved.note;
			part.entry.append(part.note);
		} else {
			part.note.remove();
		}
	}

	for (const name of keys) {
		const entry = document.createElement('div');
		entry.className = 'entry';
		const label = document.createElement('label');
		label.className = 'caption';
		label.htmlFor = `${id}-${name}`;
		const remark = document.createElement('span');
		remark.className = 'remark';
		remark.textContent = notes[name];
		label.append(labels[name], ' ', remark);
		/** @type {HTMLInputElement | HTMLTextAreaElement} */
		let input;

		if (name === 'alt' || name === 'caption') {
			input = document.createElement('textarea');
			input.className = 'cms-textarea';
			input.rows = 1;
		} else {
			input = document.createElement('input');
			input.className = 'cms-input';
			input.type = 'text';
			input.autocomplete = 'off';
		}

		input.id = `${id}-${name}`;
		input.readOnly = readonly;
		input.value = own(name);
		input.addEventListener('focus', () => {
			focused = name;
			refresh(name);
		});
		input.addEventListener('blur', () => {
			focused = null;
			refresh(name);
		});
		input.addEventListener('input', () => {
			if (name === 'attribution') attribution = input.value;
			else texts[name][key()] = input.value;

			update({ ...texts, ...(keys.includes('attribution') ? { attribution } : {}) });
		});
		const note = document.createElement('span');
		note.className = 'fallback';
		entry.append(label, input);
		element.append(entry);
		parts.set(name, { input, entry, note });
		refresh(name);
	}

	return {
		element,
		setLocale(next) {
			locale = next;

			for (const [name, part] of parts) {
				part.input.value = own(name);
				refresh(name);
			}
		},
	};
}
