import { ZXX, type LocaleMap } from '$types/data';

export type FallbackLocale = {
	id: string;
	title: string;
	fallback?: string | null;
};

export type ResolvedFallback<T> = {
	value: T;
	locale: string;
};

export function filled(value: unknown): boolean {
	return (
		value !== null &&
		value !== undefined &&
		value !== '' &&
		(!Array.isArray(value) || value.length > 0)
	);
}

export function resolveFallback<T>(
	map: LocaleMap<T> | undefined,
	locale: string,
	locales: FallbackLocale[],
	hasValue: (value: T | undefined) => boolean = filled,
): ResolvedFallback<T> | null {
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

export function resolveTextFallback(
	override: LocaleMap<string> | undefined,
	catalog: LocaleMap<string> | undefined,
	locale: string,
	locales: FallbackLocale[],
): ResolvedFallback<string> | null {
	if (filled(override?.[locale])) {
		return null;
	}

	const perUse = resolveFallback(override, locale, locales);

	if (perUse) {
		return perUse;
	}

	const current = catalog?.[locale];

	if (filled(current)) {
		return { value: current ?? '', locale };
	}

	return resolveFallback(catalog, locale, locales);
}

export function localeTitle(locales: FallbackLocale[], locale: string): string {
	return locales.find((entry) => entry.id === locale)?.title ?? locale.toUpperCase();
}
