// The per-use texts of a media item: an image's alt text and caption, a
// video's caption, a file's title. An empty text falls back to the item's
// other languages and then to the asset's catalog, shown as placeholder.

/** @import { FileItem, LocaleMap, Meta, UploadType } from '../../types/data' */
/** @import { ControlLocales } from '../../lib/control.js' */

import { ZXX, pruneItemMeta } from '../../lib/content.js';
import { fallbackLabel } from '../../lib/control.js';
import { resolveTextFallback } from '../../lib/fallback.js';
import { __ } from '../../lib/locale.js';

/** @typedef {'alt' | 'caption' | 'title'} Key */

let ids = 0;

/**
 * @typedef {object} MetaFieldsOptions
 * @property {FileItem} item
 * @property {UploadType} kind
 * @property {boolean} translate
 * @property {string} contentLocale
 * @property {ControlLocales | undefined} locales
 * @property {Meta | undefined} catalog The asset's catalog meta.
 * @property {(item: FileItem) => void} update Receives the item with pruned meta on every edit, so
 *     empty texts never shadow the catalog defaults.
 * @property {boolean} [readonly]
 */

/**
 * @param {MetaFieldsOptions} options
 * @returns {{ element: HTMLElement, setLocale: (locale: string) => void }}
 */
export function metaFields({
	item,
	kind,
	translate,
	contentLocale,
	locales,
	catalog,
	update,
	readonly = false,
}) {
	const id = `cms-media-meta-${++ids}`;
	/** @type {Key[]} */
	const keys = kind === 'image' ? ['alt', 'caption'] : kind === 'video' ? ['caption'] : ['title'];
	const labels = {
		alt: __('image:alt-text'),
		caption: __('image:caption'),
		title: __('common:title'),
	};
	const notes = {
		alt: __('image:alt-text-note'),
		caption: __('field:optional'),
		title: __('field:optional'),
	};
	/** @type {Record<string, LocaleMap<string>>} */
	const texts = Object.fromEntries(keys.map((name) => [name, { ...(item.meta?.[name] ?? {}) }]));
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
	function fallback(name) {
		return focused === name
			? null
			: resolveTextFallback(texts[name], catalog?.[name], key(), all, catalogLocale());
	}

	/** @param {Key} name */
	function placeholder(name) {
		const resolved = fallback(name);

		if (resolved) return resolved.value;
		if (name === 'alt') return __('image:alt-text-placeholder');
		if (name === 'title') return __('media:title-placeholder');

		return kind === 'video' ? __('video:caption-placeholder') : __('image:caption-placeholder');
	}

	/** @param {Key} name */
	function refresh(name) {
		const part = parts.get(name);

		if (!part) return;

		part.input.placeholder = placeholder(name);
		const resolved = fallback(name);

		if (resolved) {
			part.note.textContent = fallbackLabel(resolved, all);
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

		if (name === 'caption') {
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
		input.value = texts[name][key()] ?? '';
		input.addEventListener('focus', () => {
			focused = name;
			refresh(name);
		});
		input.addEventListener('blur', () => {
			focused = null;
			refresh(name);
		});
		input.addEventListener('input', () => {
			texts[name][key()] = input.value;
			update(pruneItemMeta({ ...item, meta: { ...item.meta, ...texts } }));
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
				part.input.value = texts[name][key()] ?? '';
				refresh(name);
			}
		},
	};
}
