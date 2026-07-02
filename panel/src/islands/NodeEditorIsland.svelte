<script lang="ts">
	import { onMount } from 'svelte';
	import type { Node as NodeType } from '$types/data';
	import { installBridge } from '$lib/bridge-install';
	import { configureRuntime, navigate } from '$lib/runtime';
	import req from '$lib/req';
	import { save as saveNode } from '$lib/node';
	import {
		currentFields,
		currentNode,
		dirty,
		error as errorToast,
		setPristine,
		success,
	} from '$lib/state';
	import { setup } from '$lib/sys';
	import Modal from '$shell/modal/Modal.svelte';
	import NodeEditor from '$shell/NodeEditor.svelte';
	import NodeEditorPlaceholder from '$shell/NodeEditorPlaceholder.svelte';
	import Toasts from '$shell/Toasts.svelte';

	type CollectionState = {
		name: string;
		slug: string;
		q?: string;
		offset?: number;
		limit?: number;
		sort?: string;
		dir?: string;
		parent?: string | null;
		view?: string;
		open?: string;
	};

	export type EditorBootstrap = {
		mode: 'edit' | 'create';
		collection: CollectionState;
		node: string | null;
		type: string | null;
		parent: string | null;
		apiBase: string;
		bootUrl: string;
		panelPath: string;
	};

	type Props = {
		bootstrap: EditorBootstrap;
	};

	let { bootstrap }: Props = $props();
	let node: NodeType | null = $state(null);
	let loading = $state(true);
	let error = $state('');

	onMount(() => {
		configureRuntime({
			panelBase: bootstrap.panelPath,
			apiBase: bootstrap.apiBase,
			bootUrl: bootstrap.bootUrl,
			loginUrl: `${bootstrap.panelPath}/login`,
		});

		const uninstallBridge = installBridge();

		void load();

		const unload = (event: BeforeUnloadEvent) => {
			if (!$dirty) {
				return;
			}

			event.preventDefault();
			event.returnValue = '';
		};
		const htmxGuard = (event: Event) => {
			if (!$dirty) {
				return;
			}

			if (!window.confirm('There are unsaved changes. Leave this editor?')) {
				event.preventDefault();
			}
		};

		window.addEventListener('beforeunload', unload);
		for (const eventName of ['htmx:beforeRequest', 'htmx:before:request']) {
			document.addEventListener(eventName, htmxGuard);
		}

		return () => {
			uninstallBridge();
			window.removeEventListener('beforeunload', unload);
			for (const eventName of ['htmx:beforeRequest', 'htmx:before:request']) {
				document.removeEventListener(eventName, htmxGuard);
			}
		};
	});

	async function load(): Promise<void> {
		loading = true;
		error = '';

		try {
			await setup(window.fetch, new URL(window.location.href));

			const response = bootstrap.mode === 'create' ? await loadBlueprint() : await loadNode();

			if (!response?.ok) {
				throw new Error('The node could not be loaded.');
			}

			node = response.data as NodeType;
			currentNode.set(node);
			currentFields.set(node.fields);
			setPristine();
		} catch (caught) {
			error = caught instanceof Error ? caught.message : 'The editor could not be loaded.';
		} finally {
			loading = false;
		}
	}

	async function loadNode() {
		if (bootstrap.node === null) {
			throw new Error('The node uid is missing.');
		}

		return req.get(`node/${bootstrap.node}`, {});
	}

	async function loadBlueprint() {
		if (bootstrap.type === null) {
			throw new Error('The node type is missing.');
		}

		return req.get(`blueprint/${bootstrap.type}`, {});
	}

	async function save(publish: boolean): Promise<boolean> {
		if (node === null) {
			return false;
		}

		if (publish) {
			node.published = true;
		}

		return bootstrap.mode === 'create' ? createNode() : saveExistingNode();
	}

	async function saveExistingNode(): Promise<boolean> {
		if (node === null) {
			return false;
		}

		const result = await saveNode(node.uid, node);

		if (!result?.success) {
			return false;
		}

		const response = await req.get(`node/${result.uid}`, {});

		if (response?.ok) {
			node = response.data as NodeType;
			currentNode.set(node);
			currentFields.set(node.fields);
		}

		return true;
	}

	async function createNode(): Promise<boolean> {
		if (node === null) {
			return false;
		}

		if (bootstrap.parent !== null) {
			node.parent = bootstrap.parent;
		}

		const response = await req.post(`node/${node.type.handle}`, node);

		if (!response?.ok) {
			errorToast(response?.data?.message ?? 'Fehler beim Erstellen des Dokuments aufgetreten!');

			return false;
		}

		const result = response.data as { success: boolean; uid: string };

		if (!result.success) {
			errorToast('Fehler beim Erstellen des Dokuments aufgetreten!');

			return false;
		}

		success('Dokument erfolgreich erstellt!');
		await navigate(editorUrl(result.uid), { invalidateAll: true });

		return true;
	}

	function editorUrl(uid: string): string {
		const params = new URLSearchParams();
		const collection = bootstrap.collection;

		if (collection.q) {
			params.set('q', collection.q);
		}

		if (collection.sort) {
			params.set('sort', collection.sort);
		}

		if (collection.dir) {
			params.set('dir', collection.dir);
		}

		if (collection.offset && collection.offset > 0) {
			params.set('offset', String(collection.offset));
		}

		if (collection.limit && collection.limit !== 50) {
			params.set('limit', String(collection.limit));
		}

		if (bootstrap.parent !== null) {
			params.set('parent', bootstrap.parent);
		}

		if (collection.view) {
			params.set('view', collection.view);
		}

		if (collection.open) {
			params.set('open', collection.open);
		}

		const query = params.toString();
		const path = `${bootstrap.panelPath}/collection/${collection.slug}/${uid}`;

		return query === '' ? path : `${path}?${query}`;
	}
</script>

<Modal>
	<div class="cosray-node-editor-island">
		{#if loading}
			<NodeEditorPlaceholder collectionName={bootstrap.collection.name} />
		{:else if error !== ''}
			<div class="cosray-node-editor-message is-error">{error}</div>
		{:else if node !== null}
			<NodeEditor bind:node collection={bootstrap.collection} {save} />
		{/if}
	</div>
	<Toasts />
</Modal>
