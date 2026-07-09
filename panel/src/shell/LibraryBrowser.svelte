<script lang="ts" module>
	import type { AssetInfo } from '$types/data';

	export type LibraryItem = AssetInfo & { uid: string; thumbUrl: string };
</script>

<script lang="ts">
	import { onMount } from 'svelte';
	import { system } from '$lib/sys';
	import { __ } from '$lib/locale';
	import IcoDocument from '$shell/icons/IcoDocument.svelte';

	type Props = {
		// image and video restrict the listing; a file context accepts
		// every kind, so it browses the whole pool.
		kind?: 'image' | 'video' | 'file' | null;
		pick: (item: LibraryItem) => void;
		selected?: string | null;
	};

	let { kind = null, pick, selected = null }: Props = $props();

	let q = $state('');
	let items: LibraryItem[] = $state([]);
	let page = $state(1);
	let more = $state(false);
	let loading = $state(false);
	let failed = $state(false);

	async function load(reset: boolean) {
		loading = true;
		failed = false;
		const params = new URLSearchParams();

		if (kind === 'image' || kind === 'video') {
			params.set('kind', kind);
		}

		if (q.trim() !== '') {
			params.set('q', q.trim());
		}

		params.set('page', String(reset ? 1 : page + 1));

		try {
			const response = await fetch(`${$system.prefix}/media/library?${params.toString()}`, {
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'X-Requested-With': 'xmlhttprequest',
				},
			});
			const data = (await response.json()) as {
				ok: boolean;
				assets: LibraryItem[];
				page: number;
				more: boolean;
			};

			if (data.ok) {
				items = reset ? data.assets : [...items, ...data.assets];
				page = data.page;
				more = data.more;
			} else {
				failed = true;
			}
		} catch {
			failed = true;
		}

		loading = false;
	}

	function search(event: Event) {
		event.preventDefault();
		void load(true);
	}

	onMount(() => void load(true));
</script>

<div class="cms-library">
	<form class="cms-library-search" onsubmit={search}>
		<input class="cms-input" type="search" placeholder={__('media:search-filename')} bind:value={q} />
		<button type="submit" class="cms-button">{__('common:search')}</button>
	</form>
	{#if failed}
		<div class="cms-library-empty">{__('media:library-load-failed')}</div>
	{:else if items.length === 0 && !loading}
		<div class="cms-library-empty">{__('media:no-files')}</div>
	{:else}
		<div class="cms-library-grid">
			{#each items as item (item.uid)}
				{#if item.kind === 'image'}
					<button
						type="button"
						class="cms-library-image"
						class:active={selected !== null && (selected === item.uid || selected === item.url)}
						title={item.filename}
						onclick={() => pick(item)}
					>
						<img src={item.thumbUrl} alt={item.filename} loading="lazy" />
						<span class="cms-library-name">{item.filename}</span>
					</button>
				{:else}
					<button
						type="button"
						class="cms-library-file"
						class:active={selected !== null && (selected === item.uid || selected === item.url)}
						title={item.filename}
						onclick={() => pick(item)}
					>
						<IcoDocument />
						<span class="cms-library-name">{item.filename}</span>
					</button>
				{/if}
			{/each}
		</div>
	{/if}
	{#if loading}
		<div class="cms-library-loading">{__('common:loading')}</div>
	{:else if more}
		<button type="button" class="cms-button cms-library-more" onclick={() => void load(false)}>
			{__('common:load-more')}
		</button>
	{/if}
</div>

<style>
	@layer panel {
		.cms-library {
			display: flex;
			flex-direction: column;
			gap: var(--space-4);
			min-width: min(48rem, 80vw);
		}

		.cms-library-search {
			display: flex;
			gap: var(--space-2);
		}

		.cms-library-search input {
			flex: 1 1 auto;
		}

		.cms-library-grid {
			display: flex;
			flex-direction: row;
			flex-wrap: wrap;
			gap: var(--space-3);
			max-height: 50vh;
			overflow-y: auto;
			align-content: flex-start;
		}

		.cms-library-image {
			position: relative;
			display: flex;
			flex-direction: column;
			width: 9rem;
			height: 9rem;
			align-items: center;
			justify-content: center;
			border: 1px solid var(--color-neutral-300);
			border-radius: var(--radius-md);
			background-color: var(--color-neutral-100);
			padding: var(--space-1);
			cursor: pointer;
			overflow: hidden;
		}

		.cms-library-image img {
			max-width: 100%;
			max-height: 100%;
		}

		.cms-library-file {
			display: flex;
			width: 100%;
			flex-direction: row;
			align-items: center;
			gap: var(--space-2);
			border: 1px solid var(--color-neutral-300);
			border-radius: var(--radius-md);
			background-color: var(--color-neutral-100);
			padding: var(--space-2) var(--space-3);
			cursor: pointer;
			text-align: left;
		}

		.cms-library-image.active,
		.cms-library-file.active {
			border-color: var(--color-info);
			outline: 2px solid var(--color-info);
		}

		.cms-library-image .cms-library-name {
			position: absolute;
			left: var(--space-1);
			right: var(--space-1);
			bottom: var(--space-1);
			border-radius: var(--radius);
			background-color: rgba(255, 255, 255, 0.85);
			font-size: var(--font-size-xs);
			color: var(--color-neutral-600);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.cms-library-file .cms-library-name {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.cms-library-empty,
		.cms-library-loading {
			color: var(--color-neutral-600);
			padding: var(--space-4) 0;
		}

		.cms-library-more {
			align-self: center;
		}
	}
</style>
