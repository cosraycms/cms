import { mount, unmount } from 'svelte';
import Editor, { type EditorBootstrap } from './Editor.svelte';

declare global {
	interface Window {
		CosrayNodeEditor?: {
			mount: typeof mountEditor;
		};
	}
}

let active: { instance: Record<string, unknown>; host: HTMLElement } | null = null;

// The editor registers document-level beforeunload/htmx guards in onMount and
// only removes them from onDestroy. A boosted navigation swaps the host out of
// the DOM but leaves the component (and its guards) alive, so a dirty editor
// would keep prompting after you have left it. Explicitly unmount the previous
// instance once its host is gone.
function releaseActive(): void {
	if (active === null || active.host.isConnected) {
		return;
	}

	void unmount(active.instance);
	active = null;
}

function readBootstrap(): EditorBootstrap | null {
	const script = document.getElementById('cosray-node-editor-data');

	if (!(script instanceof HTMLScriptElement)) {
		return null;
	}

	try {
		return JSON.parse(script.textContent ?? '') as EditorBootstrap;
	} catch (error) {
		console.error('Could not parse Cosray editor bootstrap data.', error);

		return null;
	}
}

export function mountEditor(root: ParentNode = document): void {
	releaseActive();

	const target = root.querySelector('[data-cosray-node-editor]');

	if (!(target instanceof HTMLElement) || target.dataset.cosrayNodeEditorMounted === 'true') {
		return;
	}

	const bootstrap = readBootstrap();

	if (bootstrap === null) {
		return;
	}

	target.replaceChildren();
	const instance = mount(Editor, {
		target,
		props: {
			bootstrap,
		},
	});
	target.dataset.cosrayNodeEditorMounted = 'true';
	active = { instance, host: target };
}

window.CosrayNodeEditor = {
	mount: mountEditor,
};
