import { assetsBase, panelBase } from './runtime.js';

/** @type {Map<string, Promise<unknown>>} */
const modules = new Map();

/**
 * Resolve a control module value to a URL.
 *
 * - `cosray:{entry}` — cosray-shipped element ('cosray' is a reserved
 *   plugin id), a module from the package's `src/elements/`.
 * - `https?://...` — used as-is.
 * - anything else — `{pluginId}/{file}`, served from the plugin's
 *   asset dir under the panel vendor route.
 *
 * @param {string} module
 * @returns {string}
 */
export function moduleUrl(module) {
	if (module.startsWith('cosray:')) {
		return `${assetsBase()}src/elements/${module.slice('cosray:'.length)}.js`;
	}

	if (/^https?:\/\//.test(module)) {
		return module;
	}

	return `${panelBase()}vendor/${module}`;
}

/**
 * @param {string} module
 * @returns {Promise<unknown>}
 */
export function loadElement(module) {
	const url = moduleUrl(module);
	let promise = modules.get(url);

	if (!promise) {
		promise = import(/* @vite-ignore */ url);
		modules.set(url, promise);
	}

	return promise;
}
