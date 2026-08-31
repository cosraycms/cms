import '../styles/panel.css';

import type { BridgeSystem } from '$lib/bridge';

import { install as installBulk } from './behaviors/bulk';
import { install as installChrome } from './behaviors/chrome';
import { install as installDirty } from './behaviors/dirty';
import { install as installErrors } from './behaviors/errors';
import { install as installMenu } from './behaviors/menu';
import { install as installMenuKeys } from './behaviors/menu-keys';
import { install as installMenuTree } from './behaviors/menu-tree';
import { install as installRepeater } from './behaviors/repeater';
import { install as installSubmit } from './behaviors/submit';
import { install as installTabs } from './behaviors/tabs';
import { install as installTransport } from './behaviors/transport';
import { install as installWhen } from './behaviors/when';
import { installBridge } from '$lib/bridge-standalone';
import { loadElement } from '$lib/elements';
import { installHost } from '$lib/host';
import { configureRuntime } from '$lib/runtime';

const cleanups: Array<() => void> = [];

function listen<K extends keyof DocumentEventMap>(
	type: K,
	listener: (event: DocumentEventMap[K]) => void,
): void {
	document.addEventListener(type, listener);
	cleanups.push(() => document.removeEventListener(type, listener));
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

// Full-page island elements (e.g. the media library) carry a
// data-cosray-element marker naming their cosray element bundle. They
// mount themselves once defined, so we only have to load the bundle.
// bootEditor runs first, so the runtime base and bridge are ready.
function bootElements(): void {
	document.querySelectorAll<HTMLElement>('[data-cosray-element]').forEach((mount) => {
		const name = mount.dataset.cosrayElement;

		if (name) {
			void loadElement(`cosray:${name}`);
		}
	});
}

function afterSwap(): void {
	bootEditor();
	bootElements();
}

listen('keydown', focusSearch);
cleanups.push(
	installDirty(),
	installTabs(),
	installRepeater(),
	installChrome(),
	installWhen(),
	installSubmit(),
	installTransport(),
	installErrors(),
	installBulk(),
	installMenu(),
	installMenuTree(),
	installMenuKeys(),
);
listen('htmx:after:swap' as keyof DocumentEventMap, afterSwap);

// bootEditor first: defining cosray-host upgrades the hosts already parsed
// into the page, and each upgrade resolves its module against the panel base
// the payload carries. Defining earlier resolves against the default base and
// every control on a first page load fails to load.
bootEditor();
installHost();
bootElements();

if (import.meta.hot) {
	import.meta.hot.dispose(() => {
		while (cleanups.length > 0) {
			cleanups.pop()?.();
		}
	});
}
