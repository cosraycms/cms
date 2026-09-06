import { describe, expect, it } from 'vitest';
import { filled, localeTitle, resolveFallback, resolveTextFallback } from '../../src/lib/fallback';

const locales = [
	{ id: 'en', title: 'English' },
	{ id: 'de', title: 'Deutsch', fallback: 'en' },
	{ id: 'de-CH', title: 'Deutsch (Schweiz)', fallback: 'de' },
	{ id: 'fr', title: 'Français' },
];

describe('fallback resolution', () => {
	it('follows the configured chain and reports the supplying locale', () => {
		expect(resolveFallback({ en: 'Hello', de: '', 'de-CH': '' }, 'de-CH', locales)).toEqual({
			value: 'Hello',
			locale: 'en',
		});
	});

	it('uses neutral content only after the configured chain', () => {
		expect(resolveFallback({ en: '', zxx: 'Shared' }, 'de', locales)).toEqual({
			value: 'Shared',
			locale: 'zxx',
		});
	});

	it('does not invent a fallback for a locale without one', () => {
		expect(resolveFallback({ en: 'Hello' }, 'fr', locales)).toBeNull();
	});

	it('stops safely when the configured chain cycles', () => {
		const cyclic = [
			{ id: 'a', title: 'A', fallback: 'b' },
			{ id: 'b', title: 'B', fallback: 'a' },
		];

		expect(resolveFallback({ a: '', b: '' }, 'a', cyclic)).toBeNull();
	});

	it('accepts a type-specific presence check without mutating the map', () => {
		const map = { en: [{ uid: '' }], de: [], zxx: [{ uid: 'asset' }] };
		const snapshot = structuredClone(map);
		const result = resolveFallback(
			map,
			'de',
			locales,
			(items) => items?.some((item) => item.uid !== '') ?? false,
		);

		expect(result).toEqual({ value: [{ uid: 'asset' }], locale: 'zxx' });
		expect(map).toEqual(snapshot);
	});

	it('treats false and zero as values but empty collections as missing', () => {
		expect(filled(false)).toBe(true);
		expect(filled(0)).toBe(true);
		expect(filled('0')).toBe(true);
		expect(filled([])).toBe(false);
	});

	it('resolves text overrides before catalog defaults without filling the target', () => {
		const overrides = { en: 'Custom English', de: '' };
		const catalog = { en: 'Catalog English', de: 'Katalog Deutsch' };

		expect(resolveTextFallback(overrides, catalog, 'de', locales)).toEqual({
			value: 'Custom English',
			locale: 'en',
		});
		expect(resolveTextFallback({ de: 'Eigener Text' }, catalog, 'de', locales)).toBeNull();
		expect(resolveTextFallback({ de: '' }, catalog, 'de', locales)).toEqual({
			value: 'Katalog Deutsch',
			locale: 'de',
		});
		expect(resolveTextFallback({ de: '' }, { en: 'Catalog English' }, 'de', locales)).toEqual({
			value: 'Catalog English',
			locale: 'en',
		});
		expect(overrides.de).toBe('');
	});

	it('uses configured titles and a readable neutral label', () => {
		expect(localeTitle(locales, 'de')).toBe('Deutsch');
		expect(localeTitle(locales, 'zxx')).toBe('ZXX');
	});
});
