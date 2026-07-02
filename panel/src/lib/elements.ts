import { panelBase } from '$lib/runtime';

const modules = new Map<string, Promise<unknown>>();

/**
 * Resolve a control module value to a URL.
 *
 * - `cosray:{entry}` — cosray-shipped element ('cosray' is a reserved
 *   plugin id): served from the panel build in production, from the
 *   Vite dev server in development.
 * - `https?://...` — used as-is.
 * - anything else — `{pluginId}/{file}`, served from the plugin's
 *   asset dir under the panel vendor route.
 */
export function moduleUrl(module: string): string {
	const base = panelBase();

	if (module.startsWith('cosray:')) {
		const entry = module.slice('cosray:'.length);

		if (import.meta.env.DEV) {
			// Indirection keeps Vite's static new URL() analysis from
			// emitting the source files as build assets.
			const source = '/src/elements/' + entry + '.ts';

			return new URL(source, import.meta.url).href;
		}

		return `${base}build/elements/${entry}.js`;
	}

	if (/^https?:\/\//.test(module)) {
		return module;
	}

	return `${base}vendor/${module}`;
}

export function loadElement(module: string): Promise<unknown> {
	const url = moduleUrl(module);
	let promise = modules.get(url);

	if (!promise) {
		if (!import.meta.env.DEV && module.startsWith('cosray:')) {
			ensureCss();
		}

		promise = import(/* @vite-ignore */ url);
		modules.set(url, promise);
	}

	return promise;
}

function ensureCss(): void {
	const id = 'cosray-elements-css';

	if (document.getElementById(id)) {
		return;
	}

	const link = document.createElement('link');
	link.id = id;
	link.rel = 'stylesheet';
	link.href = `${panelBase()}build/elements/style.css`;
	link.onerror = () => link.remove();
	document.head.append(link);
}
