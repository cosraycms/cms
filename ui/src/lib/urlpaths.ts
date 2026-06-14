import type { Route, Node } from '$types/data';
import type { System } from '$lib/sys';
import { localesMap } from '$lib/sys';
import { error } from '$lib/state';

export function generatePaths(node: Node, route: Route, system: System) {
	const paths = {};

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

		if (value) {
			switch (value.type) {
				case 'number':
				case 'date':
				case 'time':
				case 'option':
				case 'datetime':
					if (value.value) {
						path = path.replace(`{${param}}`, slugify(value.value.toString(), null));
					}
					break;
				case 'text':
					path = path.replace(
						`{${param}}`,
						getTextValue(param, value.value, system, localeId),
					);
					break;
				default:
					error('Unsupported value type');
			}
		}
	});

	return path;
}

function getTextValue(
	param: string,
	value: string | Record<string, string>,
	system: System,
	localeId: string,
) {
	if (!value) {
		return '{' + param + '}';
	}

	if (typeof value === 'string') {
		return slugify(value, system.transliterate);
	} else {
		const map = localesMap(system.locales);
		let locale = map[localeId];

		while (locale) {
			const lvalue = value?.[locale.id];

			if (lvalue) {
				return slugify(lvalue, system.transliterate);
			}

			locale = map[locale.fallback];
		}
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
