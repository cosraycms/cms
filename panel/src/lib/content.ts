import type { FileItem, LocaleMap, Meta } from '$types/data';
import { ZXX } from '$types/data';

export function neutral<T>(value: LocaleMap<T> | undefined, fallback: T): T {
	if (!value) return fallback;
	return (value[ZXX] ?? fallback) as T;
}

export function ensureNeutral<T>(value: LocaleMap<T> | undefined, fallback: T): LocaleMap<T> {
	if (!value) return { [ZXX]: fallback };
	if (!(ZXX in value)) value[ZXX] = fallback;
	return value;
}

export function ensureLocales<T>(
	value: LocaleMap<T> | undefined,
	fallback: T,
	locales: { id: string }[],
): LocaleMap<T> {
	const result = value ?? {};
	for (const locale of locales) {
		if (!(locale.id in result)) result[locale.id] = fallback;
	}
	return result;
}

export function ensureMeta(data: { meta?: Meta }): Meta {
	data.meta ??= {};
	return data.meta;
}

export function ensureMetaValue<T>(data: { meta?: Meta }, key: string, fallback: T): LocaleMap<T> {
	const meta = ensureMeta(data);
	meta[key] ??= { [ZXX]: fallback };
	return meta[key] as LocaleMap<T>;
}

export function ensureFiles(value: LocaleMap<FileItem[]> | undefined): LocaleMap<FileItem[]> {
	return ensureNeutral(value, []);
}

/**
 * Drop meta keys whose locale maps hold no actual text, and the meta
 * member itself when nothing remains. Empty per-use meta must not be
 * persisted — it would shadow the asset's catalog defaults.
 */
export function pruneItemMeta(item: FileItem): FileItem {
	if (!item.meta) {
		return item;
	}

	const meta: Meta = {};

	for (const [key, map] of Object.entries(item.meta)) {
		if (map && Object.values(map).some((value) => value !== '' && value != null)) {
			meta[key] = map;
		}
	}

	if (Object.keys(meta).length === 0) {
		return { uid: item.uid };
	}

	return { ...item, meta };
}

export function uid(length = 13): string {
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
