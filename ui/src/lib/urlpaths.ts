import { ZXX, type LocaleMap, type Route, type Node } from '$types/data';
import type { Locale, System } from '$lib/sys';
import { localesMap } from '$lib/sys';
import { error } from '$lib/state';

export function generatePaths(node: Node, route: Route, system: System) {
	const paths: Record<string, string> = {};

	[...system.locales].map(locale => {
		const path = typeof route === 'string' ? route : route[locale.id];

		if (path) {
			paths[locale.id] = transformPath(path, node, locale.id, system);
		}
	});

	return paths;
}

function transformPath(path: string, node: Node, localeId: string, system: System) {
	const routePattern = /[^{}]+(?=})/g;
	const extractParams = path.match(routePattern);

	if (!extractParams) {
		return path;
	}

	extractParams.map(param => {
		if (param === 'uid') {
			path = path.replace('{uid}', node.uid);
			return;
		}

		if (param === 'handle') {
			if (node.handle) {
				path = path.replace('{handle}', node.handle);
			} else {
				error('A handle is required for this URL path.');
			}
			return;
		}

		const value = node.content[param];

		if (value && 'value' in value) {
			const text = getTextValue(param, value.value as LocaleMap<unknown>, system, localeId);

			if (text) {
				path = path.replace(`{${param}}`, text);
			}
		}
	});

	return path;
}

function getTextValue(param: string, value: LocaleMap<unknown>, system: System, localeId: string) {
	if (!value) {
		return '{' + param + '}';
	}

	const map = localesMap(system.locales);
	let locale: Locale | undefined = map[localeId];

	while (locale) {
		const lvalue = value?.[locale.id];

		if (typeof lvalue === 'string' || typeof lvalue === 'number') {
			return slugify(String(lvalue), system.transliterate ?? null);
		}

		locale = locale.fallback ? map[locale.fallback] : undefined;
	}

	const neutral = value?.[ZXX];

	if (typeof neutral === 'string' || typeof neutral === 'number') {
		return slugify(String(neutral), system.transliterate ?? null);
	}
}

export function slugify(orig: string, transliterate: Record<string, string> | null) {
	let value = orig.trim().replace(/\s+/g, '-');

	value = value.slice(0, 255);

	if (transliterate) {
		const newValue = value.split('').map(char => {
			const trans = transliterate[char];

			if (trans) return trans;

			return char;
		});

		value = newValue.join('');
	}

	return value
		.toLowerCase()
		.replace(/[^\w-]+/g, '')
		.replace(/--+/g, '-')
		.replace(/^-+|-+$/g, '');
}
