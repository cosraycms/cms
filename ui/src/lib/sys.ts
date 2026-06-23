import { get } from 'svelte/store';
import req from '$lib/req';
import qs from '$lib/qs';
import { writable, type Writable } from 'svelte/store';

export interface Type {
	name: string;
}

export interface Locale {
	id: string;
	title: string;
	fallback?: string;
}

export interface System {
	initialized: boolean;
	debug: boolean;
	env: string;
	csrfToken: string;
	locale: string;
	defaultLocale: string;
	locales: Locale[];
	customLocales: string[];
	logo?: string;
	theme: string[];
	assets: string;
	cache: string;
	prefix: string;
	sessionExpires: number;
	transliterate?: Record<string, string>;
	allowedFiles: {
		file: string[];
		image: string[];
		video: string[];
	};
}

export const system: Writable<System> = writable({
	initialized: false,
	debug: false,
	env: 'production',
	csrfToken: '',
	locale: 'en',
	defaultLocale: 'en',
	customLocales: [],
	theme: [],
	assets: '',
	cache: '',
	prefix: '',
	sessionExpires: 3600,
	locales: [],
	allowedFiles: {
		file: [],
		image: [],
		video: [],
	},
});

export function localesMap(locales: Locale[]) {
	return locales.reduce((map: Record<string, Locale>, current: Locale) => {
		map[current.id] = current;
		return map;
	}, {});
}

export function systemLocale(system: System): string {
	const customLocales = system.customLocales;

	return customLocales.length > 0 ? customLocales[0] : system.locale;
}

export const setup = async (fetchFn: typeof window.fetch, url: URL) => {
	const customLocales = qs.asArray(url, 'lang');
	const sys = get(system);

	if (!sys.initialized) {
		const response = await req.get(`${req.base}boot`, {}, fetchFn);

		if (!response?.ok) {
			throw new Error('Fatal error while requesting settings');
		}

		const data = response.data;
		const theme = Array.isArray(data.theme)
			? data.theme.filter(
					(entry: unknown): entry is string =>
						typeof entry === 'string' && entry.trim() !== '',
				)
			: typeof data.theme === 'string' && data.theme.trim() !== ''
				? [data.theme]
				: [];
		const sys = {
			initialized: true,
			debug: data.debug as boolean,
			env: data.env as string,
			csrfToken: data.csrfToken as string,
			locale: data.locale as string,
			defaultLocale: data.defaultLocale as string,
			locales: data.locales as Locale[],
			customLocales: customLocales as string[],
			logo: data.logo as string,
			theme,
			assets: data.assets as string,
			cache: data.cache as string,
			prefix: data.prefix as string,
			sessionExpires: data.sessionExpires as number,
			transliterate: data.transliterate as Record<string, string> | null,
			allowedFiles: data.allowedFiles as {
				file: string[];
				image: string[];
			},
		} as System;

		system.set(sys);

		return sys;
	}

	return sys;
};
