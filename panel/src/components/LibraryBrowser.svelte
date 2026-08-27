<script lang="ts">
	import type { LibraryItem } from '$lib/library';

	import { onMount } from 'svelte';
	import { fetchLibrary } from '$lib/library';
	import { system } from '$lib/sys';
	import { __ } from '$lib/locale';
	import AssetGrid from '$components/media/AssetGrid.svelte';

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
		const result = await fetchLibrary($system.prefix, { kind, q, page: reset ? 1 : page + 1 });

		if (result === null) {
			failed = true;
		} else {
			items = reset ? result.items : [...items, ...result.items];
			page = result.page;
			more = result.more;
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
		<input
			class="cms-input"
			type="search"
			placeholder={__('media:search-filename')}
			bind:value={q}
		/>
		<button type="submit" class="cms-button">{__('common:search')}</button>
	</form>
	{#if failed}
		<div class="cms-library-empty">{__('media:library-load-failed')}</div>
	{:else if items.length === 0 && !loading}
		<div class="cms-library-empty">{__('media:no-files')}</div>
	{:else}
		<div class="cms-library-grid">
			<AssetGrid {items} {selected} {pick} />
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
			gap: var(--cms-space-4);
			min-width: min(48rem, 80vw);
		}

		.cms-library-search {
			display: flex;
			gap: var(--cms-space-2);
		}

		.cms-library-search input {
			flex: 1 1 auto;
		}

		.cms-library-grid {
			max-height: 50vh;
			overflow-y: auto;
		}

		.cms-library-empty,
		.cms-library-loading {
			color: var(--cms-color-text-muted);
			padding: var(--cms-space-4) 0;
		}

		.cms-library-more {
			align-self: center;
		}
	}
</style>
