<svelte:options customElement={{ tag: 'cosray-media-library', shadow: 'none' }} />

<script lang="ts">
	import type { LibraryItem } from '$lib/library';

	import type { MediaRange } from '$lib/library';

	import { onMount } from 'svelte';
	import {
		FILTER_KINDS,
		fetchLibrary,
		readMediaState,
		sinceFor,
		writeMediaState,
	} from '$lib/library';
	import { system, ensureSystem } from '$lib/sys';
	import { __ } from '$lib/locale';
	import IcoUpload from '$components/icons/IcoUpload.svelte';
	import AssetGrid from '$components/media/AssetGrid.svelte';
	import MediaDetail from '$components/media/MediaDetail.svelte';

	const KIND_LABELS: Record<string, string> = {
		image: 'media:images',
		video: 'media:videos',
		audio: 'media:audio',
		document: 'media:documents',
	};

	const RANGES: { value: MediaRange; label: string }[] = [
		{ value: '', label: 'media:date-any' },
		{ value: '7d', label: 'media:date-7d' },
		{ value: '30d', label: 'media:date-30d' },
		{ value: 'year', label: 'media:date-year' },
	];

	ensureSystem();

	let q = $state('');
	// The last search actually applied to the listing; the URL mirrors
	// this, never the live input value.
	let committed = $state('');
	let kinds: string[] = $state([]);
	let range: MediaRange = $state('');
	let counts: Record<string, number> = $state({});
	let items: LibraryItem[] = $state([]);
	let page = $state(1);
	let more = $state(false);
	let total = $state(0);
	let loading = $state(false);
	let failed = $state(false);
	let selected: string | null = $state(null);
	let uploading = $state(false);
	let uploadErrors: { file: string; error: string }[] = $state([]);
	let uploadDone = $state(0);
	let uploadTotal = $state(0);
	let dragging = $state(false);
	// Children fire their own enter/leave pairs, so a plain boolean would
	// flicker; the overlay shows while the depth is above zero.
	let dragDepth = 0;
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
			kind: kinds,
			q,
			since: sinceFor(range),
			page: reset ? 1 : page + 1,
		});

		if (result === null) {
			failed = true;
		} else {
			items = reset ? result.items : [...items, ...result.items];
			page = result.page;
			more = result.more;
			counts = result.counts;

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

	function toggleKind(kind: string) {
		kinds = kinds.includes(kind) ? kinds.filter((entry) => entry !== kind) : [...kinds, kind];
		void load(true);
	}

	function setRange(next: MediaRange) {
		if (range !== next) {
			range = next;
			void load(true);
		}
	}

	const filtered = $derived(kinds.length > 0 || range !== '' || committed !== '');

	function reset() {
		kinds = [];
		range = '';
		q = '';
		void load(true);
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

	async function uploadOne(file: File): Promise<LibraryItem | string> {
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

			if (!data.ok) {
				return data.error ?? __('upload:failed');
			}

			return {
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
		} catch {
			return __('upload:failed');
		}
	}

	async function uploadFiles(files: File[]) {
		if (files.length === 0 || uploading) {
			return;
		}

		uploading = true;
		uploadErrors = [];
		uploadDone = 0;
		uploadTotal = files.length;
		let lastAdded: string | null = null;

		for (const file of files) {
			const result = await uploadOne(file);

			if (typeof result === 'string') {
				uploadErrors = [...uploadErrors, { file: file.name, error: result }];
			} else {
				if (!items.some((existing) => existing.uid === result.uid)) {
					items = [result, ...items];
					total += 1;
				}

				lastAdded = result.uid;
			}

			uploadDone += 1;
		}

		if (lastAdded !== null) {
			selected = lastAdded;
		}

		uploading = false;
	}

	function upload(event: Event) {
		const input = event.currentTarget as HTMLInputElement;
		void uploadFiles([...(input.files ?? [])]);
		input.value = '';
	}

	function hasFiles(event: DragEvent): boolean {
		return event.dataTransfer?.types.includes('Files') ?? false;
	}

	function dragEnter(event: DragEvent) {
		if (hasFiles(event)) {
			event.preventDefault();
			dragDepth += 1;
			dragging = true;
		}
	}

	function dragOver(event: DragEvent) {
		if (hasFiles(event)) {
			event.preventDefault();
		}
	}

	function dragLeave() {
		dragDepth = Math.max(0, dragDepth - 1);
		dragging = dragDepth > 0;
	}

	function drop(event: DragEvent) {
		if (!hasFiles(event)) {
			return;
		}

		event.preventDefault();
		dragDepth = 0;
		dragging = false;
		void uploadFiles([...(event.dataTransfer?.files ?? [])]);
	}

	function onDeleted(uid: string) {
		items = items.filter((item) => item.uid !== uid);
		total = Math.max(0, total - 1);
		selected = null;
	}

	onMount(() => {
		const state = readMediaState(location.search);
		kinds = state.kinds;
		q = state.q;
		range = state.range;
		selected = state.file;
		void load(true);
	});

	$effect(() => {
		// Mirror filters, committed search and selection into the query
		// string so the screen state survives reload and travels in links.
		const next = writeMediaState(location.href, { kinds, q: committed, range, file: selected });

		if (next !== location.href) {
			history.replaceState(history.state, '', next);
		}
	});
</script>

<div class="cms-media-workspace">
	<aside class="cms-media-rail" aria-label={__('common:filter')}>
		<div class="cms-media-rail-head">
			<span class="cms-media-rail-title">{__('common:filter')}</span>
			{#if filtered}
				<button type="button" class="cms-media-reset" onclick={reset}>
					{__('common:reset')}
				</button>
			{/if}
		</div>

		<fieldset class="cms-media-rail-group">
			<legend class="cms-media-rail-title">{__('common:type')}</legend>
			{#each FILTER_KINDS as kind (kind)}
				<label class="cms-media-check">
					<input type="checkbox" checked={kinds.includes(kind)} onchange={() => toggleKind(kind)} />
					<span class="cms-media-check-label">{__(KIND_LABELS[kind])}</span>
					<span class="cms-media-check-count">{counts[kind] ?? 0}</span>
				</label>
			{/each}
		</fieldset>

		<fieldset class="cms-media-rail-group">
			<legend class="cms-media-rail-title">{__('media:uploaded')}</legend>
			{#each RANGES as entry (entry.value)}
				<label class="cms-media-check">
					<input
						type="radio"
						name="cms-media-range"
						checked={range === entry.value}
						onchange={() => setRange(entry.value)}
					/>
					<span class="cms-media-check-label">{__(entry.label)}</span>
				</label>
			{/each}
		</fieldset>
	</aside>

	<section
		class="cms-media-pane"
		class:dragging
		aria-label={__('media:title')}
		ondragenter={dragEnter}
		ondragover={dragOver}
		ondragleave={dragLeave}
		ondrop={drop}
	>
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
					{#if uploading && uploadTotal > 1}
						<span class="cms-media-upload-progress">{uploadDone}/{uploadTotal}</span>
					{/if}
				</button>
				<input bind:this={fileInput} type="file" multiple hidden onchange={upload} />
			</div>
		</div>

		{#if uploadErrors.length > 0}
			<div class="cms-media-error">
				<ul>
					{#each uploadErrors as failure (failure.file + failure.error)}
						<li>{failure.file}: {failure.error}</li>
					{/each}
				</ul>
				<button
					type="button"
					class="cms-media-error-dismiss"
					aria-label={__('common:close')}
					onclick={() => (uploadErrors = [])}>×</button
				>
			</div>
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

		{#if dragging}
			<div class="cms-media-drop" aria-hidden="true">
				<span>
					<IcoUpload />
					{__('media:drop-to-upload')}
				</span>
			</div>
		{/if}
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

		.cms-media-rail-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: var(--cms-space-2);
		}

		.cms-media-rail-title {
			font-size: var(--cms-font-size-xs);
			font-weight: 600;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: var(--cms-color-text-subtle);
		}

		.cms-media-reset {
			border: 0;
			background: none;
			padding: 0;
			cursor: pointer;
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-text-muted);
			text-decoration: underline;
			text-underline-offset: 0.2em;
		}

		.cms-media-reset:hover {
			color: var(--cms-color-text);
		}

		.cms-media-rail-group {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-2);
			border: 0;
			padding: 0;
			margin: 0;
		}

		.cms-media-rail-group legend {
			padding: 0;
			margin-bottom: var(--cms-space-1);
		}

		.cms-media-check {
			display: flex;
			align-items: center;
			gap: var(--cms-space-2);
			font-size: var(--cms-font-size-sm);
			color: var(--cms-color-text-muted);
			cursor: pointer;
		}

		.cms-media-check input {
			accent-color: var(--cms-color-text);
		}

		.cms-media-check-label {
			flex: 1 1 auto;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.cms-media-check-count {
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-text-subtle);
			font-variant-numeric: tabular-nums;
		}

		.cms-media-pane {
			position: relative;
			display: flex;
			flex-direction: column;
			min-height: 0;
			min-width: 0;
			background-color: var(--cms-color-surface);
			border: 1px solid var(--cms-color-border-strong);
			border-radius: var(--cms-radius-md);
			overflow: hidden;
		}

		.cms-media-drop {
			position: absolute;
			inset: 0;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: var(--cms-radius-md);
			background-color: color-mix(in srgb, var(--cms-color-surface) 92%, transparent);
			box-shadow: inset 0 0 0 2px var(--cms-color-info);
			pointer-events: none;
			z-index: 1;
		}

		.cms-media-drop span {
			display: inline-flex;
			align-items: center;
			gap: var(--cms-space-2);
			font-weight: 600;
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

		.cms-media-upload-progress {
			font-variant-numeric: tabular-nums;
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
			display: flex;
			align-items: flex-start;
			justify-content: space-between;
			gap: var(--cms-space-2);
			color: var(--cms-color-danger, #b00020);
			padding: var(--cms-space-2) var(--cms-space-3);
			border-bottom: 1px solid var(--cms-color-border);
			font-size: var(--cms-font-size-sm);
		}

		.cms-media-error ul {
			list-style: none;
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-1);
		}

		.cms-media-error-dismiss {
			border: 0;
			background: none;
			cursor: pointer;
			color: inherit;
			font-size: var(--cms-font-size-base);
			line-height: 1;
			padding: 0;
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

			.cms-media-rail {
				flex-direction: row;
				flex-wrap: wrap;
				align-items: flex-start;
				column-gap: var(--cms-space-6);
			}

			.cms-media-rail-head {
				flex-basis: 100%;
			}

			.cms-media-rail,
			.cms-media-pane,
			.cms-media-inspector {
				min-height: auto;
			}
		}
	}
</style>
