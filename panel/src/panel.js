// The panel's entry, loaded on every panel page: installs the behaviors,
// which listen on the document so htmx swaps cannot orphan them, and on
// editor pages the runtime and bridge the element controls rely on.

/** @import { BridgeSystem } from './lib/bridge.js' */

import { install as installActionMenus } from './lib/action-menu.js';
import { install as installBlockCatalog } from './behaviors/block-catalog.js';
import { install as installBlockCorners } from './behaviors/block-corners.js';
import { install as installBlocks } from './behaviors/blocks.js';
import { install as installBulk } from './behaviors/bulk.js';
import { install as installChrome } from './behaviors/chrome.js';
import { install as installDirty } from './behaviors/dirty.js';
import { install as installErrors } from './behaviors/errors.js';
import { install as installFallbacks } from './behaviors/fallbacks.js';
import { install as installGhosts } from './behaviors/ghosts.js';
import { install as installHeading } from './behaviors/heading.js';
import { install as installInspector } from './behaviors/inspector.js';
import { install as installLayoutPreview } from './behaviors/layout-preview.js';
import { install as installMenu } from './behaviors/menu.js';
import { install as installMenuKeys } from './behaviors/menu-keys.js';
import { install as installMedia } from './behaviors/media.js';
import { install as installMenuTree } from './behaviors/menu-tree.js';
import { install as installPaths } from './behaviors/paths.js';
import { install as installPick } from './behaviors/pick.js';
import { install as installPlacement } from './behaviors/placement.js';
import { install as installRepeater } from './behaviors/repeater.js';
import { install as installRetype } from './behaviors/retype.js';
import { install as installScroll } from './behaviors/scroll.js';
import { install as installSplit } from './behaviors/split.js';
import { install as installSubmit } from './behaviors/submit.js';
import { install as installTabs } from './behaviors/tabs.js';
import { install as installContentLocales } from './behaviors/content-locales.js';
import { install as installTransport } from './behaviors/transport.js';
import { install as installWhen } from './behaviors/when.js';
import { install as installYoutube } from './behaviors/youtube.js';
import { installBridge } from './lib/bridge-standalone.js';
import { installHost } from './lib/host.js';
import { configureRuntime } from './lib/runtime.js';

/**
 * @param {KeyboardEvent} event
 */
function focusSearch(event) {
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
function bootEditor() {
	const script = document.getElementById('cosray-system-data');

	if (!(script instanceof HTMLScriptElement)) {
		return;
	}

	try {
		const data = /** @type {{ panel: string; system: BridgeSystem }} */ (
			JSON.parse(script.textContent ?? '')
		);
		configureRuntime({ panelBase: data.panel });
		installBridge(data.system);
	} catch (error) {
		console.error('Could not parse the editor system payload.', error);
	}
}

document.addEventListener('keydown', focusSearch);
installActionMenus();
installDirty();
installContentLocales();
installTabs();
installInspector();
installRepeater();
installBlocks();
installPlacement();
installHeading();
installBlockCorners();
installGhosts();
installPick();
installSplit();
installRetype();
installBlockCatalog();
installChrome();
installLayoutPreview();
installScroll();
installWhen();
installYoutube();
installSubmit();
installTransport();
installErrors();
installFallbacks();
installPaths();
installBulk();
installMenu();
installMenuTree();
installMenuKeys();
installMedia();
document.addEventListener('htmx:after:swap', bootEditor);

// bootEditor first: defining cosray-host upgrades the hosts already parsed
// into the page, and each upgrade resolves its module against the panel base
// the payload carries. Defining earlier resolves against the default base and
// every control on a first page load fails to load.
bootEditor();
installHost();
