<script lang="ts">
	import type { BeforeNavigate } from '@sveltejs/kit';
	import type { Node } from '$types/data';
	import type { ModalFunctions } from '$shell/modal';

	import { getContext } from 'svelte';
	import { beforeNavigate, goto } from '$app/navigation';
	import { _ } from '$lib/locale';
	import { dirty, setPristine } from '$lib/state';
	import { previewRoutePaths, routePathPreviewPayload } from '$lib/urlpaths';
	import NodeControlBar from '$shell/NodeControlBar.svelte';
	import Breadcrumbs from '$shell/Breadcrumbs.svelte';
	import Headline from '$shell/Headline.svelte';
	import Document from '$shell/Document.svelte';
	import Pane from '$shell/Pane.svelte';
	import Tabs from '$shell/Tabs.svelte';
	import Content from '$shell/Content.svelte';
	import Settings from '$shell/Settings.svelte';
	import ModalDirty from '$shell/modals/ModalDirty.svelte';

	let { open, close } = getContext<ModalFunctions>('modal');

	beforeNavigate(({ cancel, to }: BeforeNavigate) => {
		if ($dirty) {
			if (to === null) {
				cancel();
			} else {
				cancel();
				open(
					ModalDirty,
					{
						proceed: () => {
							setPristine();
							close();
							goto(to.url);
						},
						close,
					},
					{
						hideClose: true,
					},
				);
			}
		}
	});

	type Props = {
		node: Node;
		collection: {
			name: string;
			slug: string;
			q?: string;
			offset?: number;
			limit?: number;
			sort?: string;
			dir?: string;
		};
		save: (published: boolean) => Promise<void>;
	};

	let { node = $bindable(), collection, save }: Props = $props();

	let activeTab = $state('content');
	let showPreview: string | null = $state(null);
	let pathPreviewRequest = 0;
	let collectionPath = $derived.by(() => {
		const params = new URLSearchParams();

		if (collection.q) {
			params.set('q', collection.q);
		}

		params.set('offset', String(collection.offset ?? 0));
		params.set('limit', String(collection.limit ?? 50));

		if (collection.sort) {
			params.set('sort', collection.sort);
		}

		if (collection.dir) {
			params.set('dir', collection.dir);
		}

		const query = params.toString();

		if (query === '') {
			return `collection/${collection.slug}`;
		}

		return `collection/${collection.slug}?${query}`;
	});

	function changeTab(tab: string) {
		return () => {
			activeTab = tab;
		};
	}

	async function preview() {
		await save(false);
		showPreview = node.paths.de;
	}

	$effect(() => {
		if (!node.route) {
			return;
		}

		const request = ++pathPreviewRequest;
		const type = node.type.handle;
		const payload = routePathPreviewPayload(node);

		void previewRoutePaths(type, payload)
			.then(paths => {
				if (request === pathPreviewRequest) {
					node.generatedPaths = paths ?? {};
				}
			})
			.catch(() => {
				if (request === pathPreviewRequest) {
					node.generatedPaths = {};
				}
			});
	});
</script>

<div class="cms-node-shell">
	<NodeControlBar
		bind:uid={node.uid}
		{collectionPath}
		deletable={node.deletable}
		preview={node.type.routable && node.type.renderable ? preview : null}
		{save} />
	<Document>
		<Breadcrumbs
			href={collectionPath}
			name={collection.name} />
		<Headline
			published={node.published}
			showPublished={node.type.renderable}>
			{@html node.title}
		</Headline>
		<Tabs>
			<button
				onclick={changeTab('content')}
				class:active={activeTab === 'content'}
				class="tab">
				{_('Inhalt')}
			</button>
			{#if node.type.routable || node.type.renderable}
				<button
					onclick={changeTab('settings')}
					class:active={activeTab === 'settings'}
					class="tab">
					{_('Einstellungen')}
				</button>
			{/if}
		</Tabs>
		<Pane>
			{#if activeTab === 'content'}
				<Content
					bind:fields={node.fields}
					bind:node />
			{:else}
				<Settings bind:node />
			{/if}
		</Pane>
	</Document>
</div>
{#if showPreview}
	<div class="preview">
		<button
			onclick={() => (showPreview = null)}
			class="cms-preview-close">
			schließen
		</button>
		<iframe
			src="/preview{showPreview}"
			title="Preview">
		</iframe>
	</div>
{/if}

<style lang="postcss">
	.preview {
		z-index: 999;
		background-color: color-mix(in srgb, var(--cms-color-neutral-900) 50%, transparent);
		backdrop-filter: blur(0.5rem);
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;

		button {
			position: absolute;
			top: 5px;
			right: 5px;
		}

		iframe {
			width: 90vw;
			height: 90vh;
			margin-top: 5vh;
			margin-left: 5vw;
		}
	}

	.cms-node-shell {
		display: flex;
		height: 100vh;
		flex-direction: column;
	}

	.cms-preview-close {
		border: none;
		border-radius: var(--cms-radius);
		background-color: var(--cms-color-danger-700);
		padding: var(--cms-space-1) var(--cms-space-4);
		color: var(--cms-color-white);
		cursor: pointer;
	}

	.cms-preview-close:hover {
		background-color: color-mix(in srgb, var(--cms-color-danger-700) 86%, black);
	}
</style>
