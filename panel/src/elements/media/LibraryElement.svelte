<svelte:options customElement={{ tag: 'cosray-media-library', shadow: 'none' }} />

<script lang="ts">
	import { onMount } from 'svelte';
	import { system, ensureSystem } from '$lib/sys';
	import { __ } from '$lib/locale';
	import IcoDocument from '$components/icons/IcoDocument.svelte';
	import IcoUpload from '$components/icons/IcoUpload.svelte';
	import MediaDetail from '$components/media/MediaDetail.svelte';

	type Item = {
		uid: string;
		filename: string;
		url: string;
		thumbUrl: string;
		kind: string;
	};

	type Filter = 'all' | 'image' | 'video';

	ensureSystem();

	let q = $state('');
	let filter: Filter = $state('all');
	let items: Item[] = $state([]);
	let page = $state(1);
	let more = $state(false);
	let loading = $state(false);
	let failed = $state(false);
	let selected: string | null = $state(null);
	let uploading = $state(false);
	let uploadError = $state('');
	let fileInput: HTMLInputElement | undefined = $state();

	const prefix = $derived($system.prefix);
	const locales = $derived($system.locales);
	const defaultLocale = $derived($system.defaultLocale || $system.locale);

	async function load(reset: boolean) {
		loading = true;
		failed = false;
		const params = new URLSearchParams();

		if (filter !== 'all') {
			params.set('kind', filter);
		}

		if (q.trim() !== '') {
			params.set('q', q.trim());
		}

		params.set('page', String(reset ? 1 : page + 1));

		try {
			const response = await fetch(`${prefix}/media/library?${params.toString()}`, {
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
			});
			const data = (await response.json()) as {
				ok: boolean;
				assets: Item[];
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

	function setFilter(next: Filter) {
		if (filter !== next) {
			filter = next;
			void load(true);
		}
	}

	function uploadKind(type: string): string {
		if (type.startsWith('image/')) {
			return 'image';
		}

		if (type.startsWith('video/')) {
			return 'video';
		}

		return 'file';
	}

	async function upload(event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		const file = input.files?.[0];

		if (!file) {
			return;
		}

		uploading = true;
		uploadError = '';
		const body = new FormData();
		body.set('file', file);

		try {
			const response = await fetch(`${prefix}/media/${uploadKind(file.type)}`, {
				method: 'POST',
				body,
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
			});
			const data = (await response.json()) as {
				ok: boolean;
				error?: string;
				uid: string;
				filename: string;
				url: string;
				thumbUrl?: string;
				kind?: string;
			};

			if (data.ok) {
				const item: Item = {
					uid: data.uid,
					filename: data.filename,
					url: data.url,
					thumbUrl: data.thumbUrl ?? data.url,
					kind: data.kind ?? uploadKind(file.type),
				};

				if (!items.some((existing) => existing.uid === item.uid)) {
					items = [item, ...items];
				}

				selected = item.uid;
			} else {
				uploadError = data.error ?? __('upload:failed');
			}
		} catch {
			uploadError = __('upload:failed');
		}

		uploading = false;
		input.value = '';
	}

	function onDeleted(uid: string) {
		items = items.filter((item) => item.uid !== uid);
		selected = null;
	}

	onMount(() => void load(true));
</script>

<div class="cms-media">
	<div class="cms-media-toolbar">
		<form class="cms-media-search" onsubmit={search}>
			<input
				class="cms-input"
				type="search"
				placeholder={__('media:search-filename')}
				bind:value={q}
			/>
			<button type="submit" class="cms-button">{__('common:search')}</button>
		</form>

		<div class="cms-media-filters" role="group" aria-label={__('common:filter')}>
			<button
				type="button"
				class="cms-button"
				class:active={filter === 'all'}
				class:secondary={filter !== 'all'}
				onclick={() => setFilter('all')}>{__('common:all')}</button
			>
			<button
				type="button"
				class="cms-button"
				class:active={filter === 'image'}
				class:secondary={filter !== 'image'}
				onclick={() => setFilter('image')}>{__('media:images')}</button
			>
			<button
				type="button"
				class="cms-button"
				class:active={filter === 'video'}
				class:secondary={filter !== 'video'}
				onclick={() => setFilter('video')}>{__('media:videos')}</button
			>
		</div>

		<div class="cms-media-upload">
			<button
				type="button"
				class="cms-button cms-button-primary"
				disabled={uploading}
				onclick={() => fileInput?.click()}
			>
				<IcoUpload />
				{uploading ? __('upload:in-progress') : __('common:upload')}
			</button>
			<input bind:this={fileInput} type="file" hidden onchange={upload} />
		</div>
	</div>

	{#if uploadError !== ''}
		<div class="cms-media-error">{uploadError}</div>
	{/if}

	{#if failed}
		<div class="cms-media-empty">{__('media:library-load-failed')}</div>
	{:else if items.length === 0 && !loading}
		<div class="cms-media-empty">{__('media:no-files')}</div>
	{:else}
		<div class="cms-media-grid">
			{#each items as item (item.uid)}
				<button
					type="button"
					class="cms-media-tile"
					class:active={selected === item.uid}
					title={item.filename}
					onclick={() => (selected = item.uid)}
				>
					{#if item.kind === 'image'}
						<img src={item.thumbUrl} alt={item.filename} loading="lazy" />
					{:else}
						<span class="cms-media-tile-icon"><IcoDocument /></span>
					{/if}
					<span class="cms-media-tile-name">{item.filename}</span>
				</button>
			{/each}
		</div>
	{/if}

	{#if loading}
		<div class="cms-media-loading">{__('common:loading')}</div>
	{:else if more}
		<button type="button" class="cms-button cms-media-more" onclick={() => void load(false)}>
			{__('common:load-more')}
		</button>
	{/if}
</div>

{#if selected !== null}
	<MediaDetail
		uid={selected}
		{prefix}
		{locales}
		{defaultLocale}
		onClose={() => (selected = null)}
		onDeleted={() => onDeleted(selected!)}
	/>
{/if}

<style>
	@layer panel {
		.cms-media {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-4);
		}

		.cms-media-toolbar {
			display: flex;
			flex-wrap: wrap;
			gap: var(--cms-space-3);
			align-items: center;
			justify-content: space-between;
		}

		.cms-media-search {
			display: flex;
			gap: var(--cms-space-2);
			flex: 1 1 16rem;
		}

		.cms-media-search input {
			flex: 1 1 auto;
		}

		.cms-media-filters {
			display: flex;
			gap: var(--cms-space-1);
		}

		.cms-media-upload button {
			display: inline-flex;
			align-items: center;
			gap: var(--cms-space-2);
		}

		.cms-media-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(var(--cms-tile-min, 9rem), 1fr));
			gap: var(--cms-space-3);
		}

		.cms-media-tile {
			position: relative;
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			aspect-ratio: 1;
			border: 1px solid var(--cms-color-border-strong);
			border-radius: var(--cms-radius-md);
			background-color: var(--cms-color-surface-sunken);
			padding: var(--cms-space-1);
			cursor: pointer;
			overflow: hidden;
		}

		.cms-media-tile img {
			max-width: 100%;
			max-height: 100%;
			object-fit: contain;
		}

		.cms-media-tile-icon {
			font-size: 2rem;
			color: var(--cms-color-text-subtle);
		}

		.cms-media-tile.active {
			border-color: var(--cms-color-info);
			outline: 2px solid var(--cms-color-info);
		}

		.cms-media-tile-name {
			position: absolute;
			left: var(--cms-space-1);
			right: var(--cms-space-1);
			bottom: var(--cms-space-1);
			border-radius: var(--cms-radius);
			/* A plate over the thumbnail: translucent, but still panel chrome. */
			background-color: color-mix(in srgb, var(--cms-color-surface) 85%, transparent);
			padding: 0 var(--cms-space-1);
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-text-muted);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.cms-media-empty,
		.cms-media-loading {
			color: var(--cms-color-text-muted);
			padding: var(--cms-space-4) 0;
		}

		.cms-media-error {
			color: var(--cms-color-danger, #b00020);
		}

		.cms-media-more {
			align-self: center;
		}
	}
</style>
