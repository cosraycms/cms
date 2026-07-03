import '../styles/panel.css';
import '$lib/host';

import type { BridgeSystem } from '$lib/bridge';

import { install as installDirty } from './behaviors/dirty';
import { install as installRepeater } from './behaviors/repeater';
import { install as installTabs } from './behaviors/tabs';
import { installBridge } from '$lib/bridge-standalone';
import { configureRuntime } from '$lib/runtime';

const mainSelector = '#main';
const cleanups: Array<() => void> = [];

const currentPath = () => window.location.pathname.replace(/\/$/, '') || '/';

const linkPath = (link: HTMLAnchorElement) => {
	try {
		return new URL(link.href, window.location.href).pathname.replace(/\/$/, '') || '/';
	} catch {
		return '';
	}
};

function listen<K extends keyof DocumentEventMap>(
	type: K,
	listener: (event: DocumentEventMap[K]) => void,
): void {
	document.addEventListener(type, listener);
	cleanups.push(() => document.removeEventListener(type, listener));
}

function updateNavigation(): void {
	const path = currentPath();

	document.querySelectorAll('.nav-link[aria-current]').forEach((link) => {
		link.removeAttribute('aria-current');
	});

	document.querySelectorAll<HTMLAnchorElement>('.nav-link[href]').forEach((link) => {
		if (linkPath(link) === path) {
			link.setAttribute('aria-current', 'page');
		}
	});
}

function focusSearch(event: KeyboardEvent): void {
	if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) {
		return;
	}

	const target = event.target;

	if (
		target instanceof HTMLInputElement ||
		target instanceof HTMLTextAreaElement ||
		target instanceof HTMLSelectElement ||
		(target instanceof HTMLElement && target.isContentEditable)
	) {
		return;
	}

	const search = document.querySelector('.search input[type="search"]');

	if (search instanceof HTMLInputElement) {
		event.preventDefault();
		search.focus();
		search.select();
	}
}

// Editor pages embed the system payload; it configures the runtime for
// module resolution and installs the window.Cosray bridge the element
// controls rely on.
function bootEditor(): void {
	const script = document.getElementById('cosray-system-data');

	if (!(script instanceof HTMLScriptElement)) {
		return;
	}

	try {
		const data = JSON.parse(script.textContent ?? '') as {
			panel: string;
			system: BridgeSystem;
		};
		configureRuntime({ panelBase: data.panel });
		installBridge(data.system);
	} catch (error) {
		console.error('Could not parse the editor system payload.', error);
	}
}

// A boosted navigation swaps a fragment whose root is itself #main into the
// existing #main, so htmx's innerHTML swap nests the fresh #main inside the
// previous one, leaving the outer wrapper carrying the last full page's class.
// Unwrap it so a single #main (with the current page's class) sits directly
// under .main — the arrangement the layout's scroll rules assume.
function denestMain(): void {
	const outer = document.querySelector(mainSelector);
	const inner = outer?.querySelector(`:scope > ${mainSelector}`);

	if (outer && inner) {
		outer.replaceWith(inner);
	}
}

function afterSwap(): void {
	denestMain();
	updateNavigation();
	bootEditor();
}

listen('keydown', focusSearch);
cleanups.push(installDirty(), installTabs(), installRepeater());
listen('htmx:afterSwap' as keyof DocumentEventMap, afterSwap);
listen('htmx:after:swap' as keyof DocumentEventMap, afterSwap);
listen('htmx:pushedIntoHistory' as keyof DocumentEventMap, updateNavigation);
listen('htmx:after:history:update' as keyof DocumentEventMap, updateNavigation);

updateNavigation();
bootEditor();

if (import.meta.hot) {
	import.meta.hot.dispose(() => {
		while (cleanups.length > 0) {
			cleanups.pop()?.();
		}
	});
}
