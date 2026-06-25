import { mount } from 'svelte';
import NodeEditorIsland, { type EditorBootstrap } from './NodeEditorIsland.svelte';

declare global {
	interface Window {
		CosrayNodeEditor?: {
			mount: typeof mountEditor;
		};
	}
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
	const target = root.querySelector('[data-cosray-node-editor]');

	if (!(target instanceof HTMLElement) || target.dataset.cosrayNodeEditorMounted === 'true') {
		return;
	}

	const bootstrap = readBootstrap();

	if (bootstrap === null) {
		return;
	}

	target.replaceChildren();
	mount(NodeEditorIsland, {
		target,
		props: {
			bootstrap,
		},
	});
	target.dataset.cosrayNodeEditorMounted = 'true';
}

window.CosrayNodeEditor = {
	mount: mountEditor,
};
