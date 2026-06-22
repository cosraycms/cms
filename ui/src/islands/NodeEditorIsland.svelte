<script lang="ts">
	import { onMount } from 'svelte';
	import type { Node as NodeType } from '$types/data';
	import { configureRuntime } from '$lib/runtime';
	import req from '$lib/req';
	import { save as saveNode } from '$lib/node';
	import { currentFields, currentNode, dirty, setPristine } from '$lib/state';
	import { setup } from '$lib/sys';
	import Modal from '$shell/modal/Modal.svelte';
	import NodeEditor from '$shell/NodeEditor.svelte';
	import Toasts from '$shell/Toasts.svelte';

	type CollectionState = {
		name: string;
		slug: string;
		q?: string;
		offset?: number;
		limit?: number;
		sort?: string;
		dir?: string;
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
		backUrl: string;
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
		document.body.addEventListener('htmx:beforeRequest', htmxGuard);

		return () => {
			window.removeEventListener('beforeunload', unload);
			document.body.removeEventListener('htmx:beforeRequest', htmxGuard);
		};
	});

	async function load(): Promise<void> {
		loading = true;
		error = '';

		try {
			await setup(window.fetch, new URL(window.location.href));

			if (bootstrap.mode !== 'edit' || bootstrap.node === null) {
				throw new Error('The editor island can only edit existing nodes yet.');
			}

			const response = await req.get(`node/${bootstrap.node}`, {});

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

	async function save(publish: boolean): Promise<boolean> {
		if (node === null) {
			return false;
		}

		if (publish) {
			node.published = true;
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
</script>

<Modal>
	<div class="cosray-node-editor-island">
		{#if loading}
			<div class="cosray-node-editor-message">Loading editor …</div>
		{:else if error !== ''}
			<div class="cosray-node-editor-message is-error">{error}</div>
		{:else if node !== null}
			<NodeEditor
				bind:node
				collection={bootstrap.collection}
				{save} />
		{/if}
	</div>
	<Toasts />
</Modal>
