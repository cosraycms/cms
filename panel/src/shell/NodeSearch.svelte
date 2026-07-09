<script lang="ts" module>
	export type NodeInfo = { uid: string; title: string; type: string; typeLabel: string };
</script>

<script lang="ts">
	import { onMount } from 'svelte';
	import { panelBase } from '$lib/runtime';
	import { __ } from '$lib/locale';

	type Props = {
		pick: (node: NodeInfo) => void;
		// A uid whose current selection should be shown (edit mode).
		selected?: string | null;
		// A uid to keep out of results (e.g. the node being edited).
		exclude?: string | null;
	};

	let { pick, selected = null, exclude = null }: Props = $props();

	let q = $state('');
	let results: NodeInfo[] = $state([]);
	let current: NodeInfo | null = $state(null);
	let loading = $state(false);
	let failed = $state(false);
	let searched = $state(false);
	let timer: ReturnType<typeof setTimeout> | undefined;

	async function fetchNodes(path: string, params: URLSearchParams): Promise<NodeInfo[]> {
		const response = await fetch(`${panelBase()}${path}?${params.toString()}`, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
		});
		const data = (await response.json()) as { ok: boolean; nodes: NodeInfo[] };

		return data.ok ? data.nodes : [];
	}

	async function search(): Promise<void> {
		const term = q.trim();

		if (term === '') {
			results = [];
			searched = false;

			return;
		}

		loading = true;
		failed = false;
		const params = new URLSearchParams({ q: term });

		if (exclude) {
			params.set('node', exclude);
		}

		try {
			results = await fetchNodes('reference/nodes', params);
			searched = true;
		} catch {
			results = [];
			failed = true;
		}

		loading = false;
	}

	function onInput(): void {
		clearTimeout(timer);
		timer = setTimeout(() => void search(), 200);
	}

	function choose(node: NodeInfo): void {
		current = node;
		pick(node);
	}

	onMount(() => {
		if (!selected) {
			return;
		}

		const uid = selected;

		// Resolve the current target's title for display when editing.
		void fetchNodes('reference/labels', new URLSearchParams({ uids: uid }))
			.then((nodes) => {
				current = nodes[0] ?? { uid, title: uid, type: '', typeLabel: '' };
			})
			.catch(() => {});
	});
</script>

<div class="cms-nodesearch">
	{#if current}
		<div class="cms-nodesearch-current">
			<span class="cms-nodesearch-current-label">{__('media:current')}</span>
			<span class="cms-nodesearch-title">{current.title || current.uid}</span>
			{#if current.typeLabel}
				<span class="cms-nodesearch-type">{current.typeLabel}</span>
			{/if}
		</div>
	{/if}

	<input
		class="cms-input"
		type="search"
		placeholder={__('node:search-page')}
		bind:value={q}
		oninput={onInput}
	/>

	{#if failed}
		<div class="cms-nodesearch-empty">{__('search:failed')}</div>
	{:else if loading}
		<div class="cms-nodesearch-empty">{__('common:loading')}</div>
	{:else if results.length > 0}
		<ul class="cms-nodesearch-results">
			{#each results as node (node.uid)}
				<li>
					<button
						type="button"
						class="cms-nodesearch-result"
						class:active={current?.uid === node.uid}
						onclick={() => choose(node)}
					>
						<span class="cms-nodesearch-title">{node.title || node.uid}</span>
						{#if node.typeLabel}
							<span class="cms-nodesearch-type">{node.typeLabel}</span>
						{/if}
					</button>
				</li>
			{/each}
		</ul>
	{:else if searched && q.trim() !== ''}
		<div class="cms-nodesearch-empty">{__('search:no-results')}</div>
	{/if}
</div>

<style>
	@layer panel {
		.cms-nodesearch {
			display: flex;
			flex-direction: column;
			gap: var(--space-3);
			min-width: min(48rem, 80vw);
		}

		.cms-nodesearch-current {
			display: flex;
			align-items: center;
			gap: var(--space-2);
			padding: var(--space-2) var(--space-3);
			border: 1px solid var(--color-info);
			border-radius: var(--radius-md);
			background-color: var(--color-neutral-100);
		}

		.cms-nodesearch-current-label {
			font-size: var(--font-size-sm);
			color: var(--color-neutral-600);
		}

		.cms-nodesearch-results {
			display: flex;
			flex-direction: column;
			gap: var(--space-1);
			margin: 0;
			padding: 0;
			list-style: none;
			max-height: 50vh;
			overflow-y: auto;
		}

		.cms-nodesearch-result {
			display: flex;
			align-items: center;
			gap: var(--space-2);
			width: 100%;
			padding: var(--space-2) var(--space-3);
			border: 1px solid var(--color-neutral-300);
			border-radius: var(--radius-md);
			background-color: var(--color-neutral-100);
			text-align: left;
			cursor: pointer;
		}

		.cms-nodesearch-result:hover,
		.cms-nodesearch-result.active {
			border-color: var(--color-info);
			outline: 2px solid var(--color-info);
		}

		.cms-nodesearch-title {
			flex: 1 1 auto;
			min-width: 0;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.cms-nodesearch-type {
			font-size: var(--font-size-sm);
			color: var(--color-neutral-600);
		}

		.cms-nodesearch-empty {
			color: var(--color-neutral-600);
			padding: var(--space-2) 0;
		}
	}
</style>
