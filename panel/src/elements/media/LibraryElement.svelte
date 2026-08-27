<svelte:options customElement={{ tag: 'cosray-media-library', shadow: 'none' }} />

<script lang="ts">
	import type { LibraryItem } from '$lib/library';

	import { onMount } from 'svelte';
	import { fetchLibrary, readMediaState, writeMediaState } from '$lib/library';
	import { system, ensureSystem } from '$lib/sys';
	import { __ } from '$lib/locale';
	import IcoUpload from '$components/icons/IcoUpload.svelte';
	import AssetGrid from '$components/media/AssetGrid.svelte';
	import MediaDetail from '$components/media/MediaDetail.svelte';

	type Filter = 'all' | 'image' | 'video';

	ensureSystem();

	let q = $state('');
	// The last search actually applied to the listing; the URL mirrors
	// this, never the live input value.
	let committed = $state('');
	let filter: Filter = $state('all');
	let items: LibraryItem[] = $state([]);
	let page = $state(1);
	let more = $state(false);
	let total = $state(0);
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

		if (reset) {
			committed = q.trim();
		}

		const result = await fetchLibrary(prefix, {
			kind: filter === 'all' ? null : filter,
			q,
			page: reset ? 1 : page + 1,
		});

		if (result === null) {
			failed = true;
		} else {
			items = reset ? result.items : [...items, ...result.items];
			page = result.page;
			more = result.more;

			// A page past the end reports 0; only a page with rows (or a
			// fresh listing) knows the real count.
			if (reset || result.items.length > 0) {
				total = result.total;
			}
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
				mime?: string | null;
				bytes?: number | null;
				width?: number | null;
				height?: number | null;
			};

			if (data.ok) {
				const item: LibraryItem = {
					uid: data.uid,
					filename: data.filename,
					url: data.url,
					thumbUrl: data.thumbUrl ?? data.url,
					kind: data.kind ?? uploadKind(file.type),
					mime: data.mime ?? null,
					bytes: data.bytes ?? null,
					width: data.width ?? null,
					height: data.height ?? null,
				};

				if (!items.some((existing) => existing.uid === item.uid)) {
					items = [item, ...items];
					total += 1;
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
		total = Math.max(0, total - 1);
		selected = null;
	}

	onMount(() => {
		const state = readMediaState(location.search);
		filter = state.kind;
		q = state.q;
		selected = state.file;
		void load(true);
	});

	$effect(() => {
		// Mirror filter, committed search and selection into the query
		// string so the screen state survives reload and travels in links.
		const next = writeMediaState(location.href, { kind: filter, q: committed, file: selected });

		if (next !== location.href) {
			history.replaceState(history.state, '', next);
		}
	});
</script>

<div class="cms-media-workspace">
	<aside class="cms-media-rail" aria-label={__('common:filter')}>
		<div class="cms-media-rail-title">{__('common:filter')}</div>
		<div class="cms-media-kinds" role="group" aria-label={__('common:filter')}>
			<button type="button" class:active={filter === 'all'} onclick={() => setFilter('all')}>
				{__('common:all')}
			</button>
			<button type="button" class:active={filter === 'image'} onclick={() => setFilter('image')}>
				{__('media:images')}
			</button>
			<button type="button" class:active={filter === 'video'} onclick={() => setFilter('video')}>
				{__('media:videos')}
			</button>
		</div>
	</aside>

	<section class="cms-media-pane">
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

			<span class="cms-media-count">{__('media:file-count', { count: total })}</span>

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

		<div class="cms-media-scroll">
			{#if failed}
				<div class="cms-media-empty">{__('media:library-load-failed')}</div>
			{:else if items.length === 0 && !loading}
				<div class="cms-media-empty">{__('media:no-files')}</div>
			{:else}
				<AssetGrid {items} {selected} pick={(item) => (selected = item.uid)} />
			{/if}

			{#if loading}
				<div class="cms-media-loading">{__('common:loading')}</div>
			{:else if more}
				<button type="button" class="cms-button cms-media-more" onclick={() => void load(false)}>
					{__('common:load-more')}
				</button>
			{/if}
		</div>
	</section>

	<aside class="cms-media-inspector" aria-label={__('media:file-details')}>
		{#if selected !== null}
			<MediaDetail
				uid={selected}
				{prefix}
				{locales}
				{defaultLocale}
				onClose={() => (selected = null)}
				onDeleted={() => onDeleted(selected!)}
			/>
		{:else}
			<div class="cms-media-inspector-empty">{__('media:select-hint')}</div>
		{/if}
	</aside>
</div>

<style>
	@layer panel {
		.cms-media-workspace {
			flex: 1 1 auto;
			min-height: 0;
			display: grid;
			grid-template-columns: 11rem minmax(0, 1fr) minmax(18rem, 22rem);
			gap: var(--cms-space-4);
			align-items: stretch;
		}

		.cms-media-rail {
			min-height: 0;
			overflow-y: auto;
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-3);
		}

		.cms-media-rail-title {
			font-size: var(--cms-font-size-xs);
			font-weight: 600;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: var(--cms-color-text-subtle);
		}

		.cms-media-kinds {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-1);
		}

		.cms-media-kinds button {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: var(--cms-space-2);
			border: 0;
			background: none;
			text-align: left;
			cursor: pointer;
			padding: var(--cms-space-1) var(--cms-space-2);
			border-radius: var(--cms-radius);
			font-size: var(--cms-font-size-sm);
			color: var(--cms-color-text-muted);
		}

		.cms-media-kinds button:hover {
			background-color: var(--cms-color-surface);
		}

		.cms-media-kinds button.active {
			background-color: var(--cms-color-surface);
			color: var(--cms-color-text);
			font-weight: 600;
		}

		.cms-media-pane {
			display: flex;
			flex-direction: column;
			min-height: 0;
			min-width: 0;
			background-color: var(--cms-color-surface);
			border: 1px solid var(--cms-color-border-strong);
			border-radius: var(--cms-radius-md);
			overflow: hidden;
		}

		.cms-media-toolbar {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: var(--cms-space-3);
			padding: var(--cms-space-3);
			border-bottom: 1px solid var(--cms-color-border);
		}

		.cms-media-search {
			display: flex;
			gap: var(--cms-space-2);
			flex: 1 1 14rem;
		}

		.cms-media-search input {
			flex: 1 1 auto;
		}

		.cms-media-count {
			font-size: var(--cms-font-size-sm);
			color: var(--cms-color-text-muted);
			font-variant-numeric: tabular-nums;
			white-space: nowrap;
		}

		.cms-media-upload button {
			display: inline-flex;
			align-items: center;
			gap: var(--cms-space-2);
		}

		.cms-media-scroll {
			flex: 1 1 auto;
			min-height: 0;
			overflow-y: auto;
			padding: var(--cms-space-3);
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-3);
		}

		.cms-media-inspector {
			min-height: 0;
			display: flex;
			flex-direction: column;
		}

		.cms-media-inspector-empty {
			flex: 1 1 auto;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: var(--cms-space-4);
			border: 1px dashed var(--cms-color-border-strong);
			border-radius: var(--cms-radius-md);
			color: var(--cms-color-text-subtle);
			font-size: var(--cms-font-size-sm);
			text-align: center;
		}

		.cms-media-empty,
		.cms-media-loading {
			color: var(--cms-color-text-muted);
			padding: var(--cms-space-4) 0;
		}

		.cms-media-error {
			color: var(--cms-color-danger, #b00020);
			padding: var(--cms-space-2) var(--cms-space-3);
			border-bottom: 1px solid var(--cms-color-border);
		}

		.cms-media-more {
			align-self: center;
		}

		/* Stacked: panes stop scrolling internally, the page scrolls. */
		@media (max-width: 72rem) {
			.cms-media-workspace {
				display: flex;
				flex-direction: column;
			}

			.cms-media-rail,
			.cms-media-scroll {
				overflow: visible;
			}

			.cms-media-kinds {
				flex-direction: row;
				flex-wrap: wrap;
			}

			.cms-media-rail,
			.cms-media-pane,
			.cms-media-inspector {
				min-height: auto;
			}
		}
	}
</style>
