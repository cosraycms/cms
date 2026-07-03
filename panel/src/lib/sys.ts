import { get } from 'svelte/store';
import { writable, type Writable } from 'svelte/store';

import type {} from '$lib/bridge';

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

/**
 * Populates the system store from the window.Cosray bridge. Element
 * chunks import this module after the bridge is installed on editor
 * pages, so components reading the store (locale tabs, media modals)
 * get live data without the legacy boot roundtrip.
 */
export function ensureSystem(): void {
	if (get(system).initialized || typeof window === 'undefined' || !window.Cosray) {
		return;
	}

	const sys = window.Cosray.system();

	system.set({
		...get(system),
		initialized: true,
		debug: sys.debug,
		locale: sys.locale,
		defaultLocale: sys.defaultLocale,
		locales: sys.locales,
		customLocales: sys.customLocales,
		assets: sys.assets,
		prefix: sys.prefix,
		allowedFiles: sys.allowedFiles,
	});
}

ensureSystem();
