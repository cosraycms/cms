/** @import { FileItem, ItemMeta, LocaleMap, Meta } from '../types/data' */

/** The locale id of language-neutral values. */
export const ZXX = 'zxx';

/**
 * @template T
 * @param {LocaleMap<T> | undefined} value
 * @param {T} fallback
 * @returns {T}
 */
export function neutral(value, fallback) {
	if (!value) return fallback;
	return value[ZXX] ?? fallback;
}

/**
 * @template T
 * @param {LocaleMap<T> | undefined} value
 * @param {T} fallback
 * @returns {LocaleMap<T>}
 */
export function ensureNeutral(value, fallback) {
	return { [ZXX]: fallback, ...(value ?? {}) };
}

/**
 * @template T
 * @param {LocaleMap<T> | undefined} value
 * @param {T} fallback
 * @param {{ id: string }[]} locales
 * @returns {LocaleMap<T>}
 */
export function ensureLocales(value, fallback, locales) {
	const result = { ...(value ?? {}) };
	for (const locale of locales) {
		if (!(locale.id in result)) result[locale.id] = fallback;
	}
	return result;
}

/**
 * @param {{ meta?: Meta }} data
 * @returns {Meta}
 */
export function ensureMeta(data) {
	data.meta ??= {};
	return data.meta;
}

/**
 * @template T
 * @param {{ meta?: Meta }} data
 * @param {string} key
 * @param {T} fallback
 * @returns {LocaleMap<T>}
 */
export function ensureMetaValue(data, key, fallback) {
	const meta = ensureMeta(data);
	meta[key] ??= { [ZXX]: fallback };
	return meta[key];
}

/**
 * @param {LocaleMap<FileItem[]> | undefined} value
 * @returns {LocaleMap<FileItem[]>}
 */
export function ensureFiles(value) {
	return ensureNeutral(value, /** @type {FileItem[]} */ ([]));
}

/**
 * Drop meta keys whose locale maps hold no actual text, and the meta
 * member itself when nothing remains. Empty per-use meta must not be
 * persisted — it would shadow the asset's catalog defaults.
 *
 * @param {FileItem} item
 * @returns {FileItem}
 */
export function pruneItemMeta(item) {
	if (!item.meta) {
		return item;
	}

	/** @type {ItemMeta} */
	const meta = {};

	for (const [key, value] of Object.entries(item.meta)) {
		const filled =
			typeof value === 'string'
				? value.trim() !== ''
				: value && Object.values(value).some((entry) => entry !== '' && entry != null);

		if (filled) {
			meta[key] = value;
		}
	}

	if (Object.keys(meta).length === 0) {
		return { uid: item.uid };
	}

	return { ...item, meta };
}

/**
 * Replaces some of an item's meta, pruning what ends up empty.
 *
 * @param {FileItem} item
 * @param {ItemMeta} patch An absent or empty value removes its key.
 * @returns {FileItem}
 */
export function patchItemMeta(item, patch) {
	return pruneItemMeta({ ...item, meta: { ...item.meta, ...patch } });
}

export function uid(length = 13) {
	const alphabet = '123456789bcdfghklmnpqrstvwxyz';
	const threshold = Math.floor(256 / alphabet.length) * alphabet.length;
	const bytes = new Uint8Array(length * 2);
	let value = '';

	while (value.length < length) {
		crypto.getRandomValues(bytes);

		for (const byte of bytes) {
			if (byte >= threshold) continue;

			value += alphabet[byte % alphabet.length];

			if (value.length === length) break;
		}
	}

	return value;
}
