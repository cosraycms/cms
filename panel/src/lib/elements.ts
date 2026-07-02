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
			return new URL(`/src/elements/${entry}.ts`, import.meta.url).href;
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
			ensureCss(module.slice('cosray:'.length));
		}

		promise = import(/* @vite-ignore */ url);
		modules.set(url, promise);
	}

	return promise;
}

function ensureCss(entry: string): void {
	const id = `cosray-element-css-${entry}`;

	if (document.getElementById(id)) {
		return;
	}

	const link = document.createElement('link');
	link.id = id;
	link.rel = 'stylesheet';
	link.href = `${panelBase()}build/elements/${entry}.css`;
	link.onerror = () => link.remove();
	document.head.append(link);
}
