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
	import { portal } from '$lib/portal';
	import Icon from '$components/Icon.svelte';
	import AssetGrid from '$components/media/AssetGrid.svelte';
	import MediaDetail from '$components/media/MediaDetail.svelte';

	const KIND_LABELS: Record<string, string> = {
		image: __('media:images'),
		video: __('media:videos'),
		audio: __('media:audio'),
		document: __('media:documents'),
	};

	const RANGES: { value: MediaRange; label: string }[] = [
		{ value: '', label: __('media:date-any') },
		{ value: '7d', label: __('media:date-7d') },
		{ value: '30d', label: __('media:date-30d') },
		{ value: 'year', label: __('media:date-year') },
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
	let toolbar: HTMLElement | undefined = $state();
	let counter: HTMLElement | undefined = $state();
	let rail: HTMLElement | undefined = $state();
	// One panel-wide preference: an inspector is collapsed or it is not.
	let collapsed = $state(false);

	const prefix = $derived($system.prefix);
	const contentLocales = $derived($system.locales);
	// The screen's content-language selector sits outside the element: the
	// scope around it carries the selection and announces every change.
	let locale = $state('');

	function readLocale(): void {
		locale =
			$host().closest('[data-content-locale-scope]')?.getAttribute('data-content-locale') ||
			$system.defaultLocale ||
			$system.locale;
	}

	onMount(() => {
		toolbar =
			$host().closest('.cms-media')?.querySelector<HTMLElement>('[data-media-toolbar]') ??
			undefined;
		// The filter rail is the shell's, so it sits outside this element's
		// subtree, like the toolbar.
		rail = document.querySelector<HTMLElement>('[data-media-rail]') ?? undefined;
		counter =
			$host().closest('.cms-media')?.querySelector<HTMLElement>('[data-media-count]') ?? undefined;

		if (counter) {
			counter.hidden = false;
		}
		collapsed = document.cookie.includes('cosray_inspector=collapsed');
		readLocale();
		document.addEventListener('content-locale:change', readLocale);

		return () => document.removeEventListener('content-locale:change', readLocale);
	});

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

	function clearSearch() {
		q = '';
		void load(true);
	}

	function search(event: Event) {
		event.preventDefault();
		void load(true);
	}

	function edited(event: Event) {
		// Emptying the field — the native clear button included — shows the
		// full listing again without an explicit submit.
		q = (event.currentTarget as HTMLInputElement).value;

		if (q.trim() === '' && committed !== '') {
			void load(true);
		}
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

{#if counter}
	<span class="cms-media-count" use:portal={counter}>
		{__('media:file-count', { count: total })}
	</span>
{/if}

{#if toolbar}
	<div class="cms-media-toolbar" use:portal={toolbar}>
		<button
			type="button"
			class="cms-button primary"
			disabled={uploading}
			onclick={() => fileInput?.click()}
		>
			<Icon name="cloud-upload" />
			{uploading ? __('upload:in-progress') : __('common:upload')}
			{#if uploading && uploadTotal > 1}
				<span class="progress">{uploadDone}/{uploadTotal}</span>
			{/if}
		</button>
		<input bind:this={fileInput} type="file" multiple hidden onchange={upload} />
	</div>
{/if}

{#if rail}
	<div class="cms-media-rail" use:portal={rail}>
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
					<span class="cms-media-check-label">{KIND_LABELS[kind]}</span>
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
					<span class="cms-media-check-label">{entry.label}</span>
				</label>
			{/each}
		</fieldset>
	</div>
{/if}

<div class="cms-media-workspace">
	<section
		class="cms-media-pane cms-dropzone"
		class:is-dragging={dragging}
		aria-label={__('media:title')}
		ondragenter={dragEnter}
		ondragover={dragOver}
		ondragleave={dragLeave}
		ondrop={drop}
	>
		<div class="toolbar">
			<form class="search" data-hx-boost="false" onsubmit={search}>
				<span class="icon" aria-hidden="true">⌕</span>
				<input
					class="cms-input"
					type="search"
					aria-label={__('media:search-filename')}
					placeholder={__('media:search-filename')}
					value={q}
					oninput={edited}
				/>
			</form>
			{#if committed !== ''}
				<button type="button" class="cms-button secondary" onclick={clearSearch}>
					{__('common:reset')}
				</button>
			{/if}
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
					onclick={() => (uploadErrors = [])}><Icon name="x-lg" /></button
				>
			</div>
		{/if}

		<div class="cms-media-scroll">
			{#if failed}
				<div class="cms-media-empty">{__('media:library-load-failed')}</div>
			{:else if items.length === 0 && !loading}
				<div class="cms-media-empty">{__('media:no-files')}</div>
			{:else}
				<AssetGrid
					--gap="var(--cms-space-4)"
					{items}
					{selected}
					pick={(item) => (selected = item.uid)}
				/>
			{/if}

			{#if loading}
				<div class="cms-media-loading">{__('common:loading')}</div>
			{:else if more}
				<button
					type="button"
					class="cms-button secondary cms-media-more"
					onclick={() => void load(false)}
				>
					{__('common:load-more')}
				</button>
			{/if}
		</div>

		{#if dragging}
			<div class="drop" aria-hidden="true">
				<Icon name="cloud-upload" />
				{__('media:drop-to-upload')}
			</div>
		{/if}
	</section>

	<aside
		class="cms-inspector"
		aria-label={__('media:file-details')}
		data-inspector
		data-collapsed={collapsed ? '' : undefined}
	>
		<div class="strip">
			<button
				type="button"
				class="tool"
				title={__('media:details-show')}
				aria-label={__('media:details-show')}
				data-inspector-expand
			>
				<Icon name="layout-sidebar-inset-reverse" />
			</button>
			{#if contentLocales.length > 1}
				<div
					class="cms-content-locales is-vertical"
					role="radiogroup"
					aria-label={__('editor:content-language')}
					data-content-locale-control
				>
					{#each contentLocales as entry (entry.id)}
						<button
							type="button"
							class="option"
							role="radio"
							aria-checked={locale === entry.id}
							tabindex={locale === entry.id ? 0 : -1}
							title={entry.title}
							aria-label={entry.title}
							data-content-locale-option={entry.id}
						>
							{entry.id}
						</button>
					{/each}
				</div>
			{/if}
		</div>
		<div class="drawer">
			<div class="top">
				<span class="heading">{__('media:file-details')}</span>
				<button
					type="button"
					class="tool"
					title={__('media:details-hide')}
					aria-label={__('media:details-hide')}
					data-inspector-collapse
				>
					<Icon name="layout-sidebar-inset-reverse" />
				</button>
			</div>
			<div class="scroll">
				{#if contentLocales.length > 1}
					<div class="cms-field">
						<span class="label" id="cms-media-locale-label">{__('editor:content-language')}</span>
						<div
							class="cms-content-locales"
							role="radiogroup"
							aria-labelledby="cms-media-locale-label"
							data-content-locale-control
						>
							{#each contentLocales as entry (entry.id)}
								<button
									type="button"
									class="option"
									role="radio"
									aria-checked={locale === entry.id}
									tabindex={locale === entry.id ? 0 : -1}
									data-content-locale-option={entry.id}
								>
									{entry.title}
								</button>
							{/each}
						</div>
					</div>
				{/if}
				{#if selected !== null}
					<MediaDetail
						uid={selected}
						{prefix}
						{locale}
						onClose={() => (selected = null)}
						onDeleted={() => onDeleted(selected!)}
					/>
				{:else}
					<div class="cms-media-inspector-empty">{__('media:select-hint')}</div>
				{/if}
			</div>
		</div>
	</aside>
</div>

<style>
	@layer panel {
		.cms-media-workspace {
			display: flex;
			flex: 1 1 auto;
			align-items: stretch;
			min-height: 0;
		}

		.cms-media-rail {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-3);
			min-width: 0;
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
			color: var(--cms-color-text-label);
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
			flex: 1 1 auto;
			flex-direction: column;
			min-height: 0;
			min-width: 0;
			background: var(--cms-pane-bg);
			border-start-start-radius: var(--cms-pane-radius-start);
			border-start-end-radius: var(--cms-pane-radius-end);
			overflow: hidden;

			&::after {
				content: '';
				position: absolute;
				inset: 0;
				border-radius: inherit;
				box-shadow: var(--cms-pane-shadow);
				pointer-events: none;
			}
		}

		.cms-media-toolbar {
			display: flex;
			align-items: center;
			gap: var(--cms-space-3);

			& .progress {
				font-variant-numeric: tabular-nums;
			}
		}

		.cms-media-scroll {
			flex: 1 1 auto;
			min-height: 0;
			overflow-y: auto;
			padding: var(--cms-space-6);
			overscroll-behavior: contain;
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-3);
		}

		.cms-media-inspector-empty {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: var(--cms-space-4);
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
			color: var(--cms-color-danger);
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

		/* No shell: the rail is a strip above the content, the panes stop
		   scrolling internally and the page scrolls. */
		@media (width < 40rem), (height < 30rem) {
			.cms-media-workspace {
				flex-direction: column;
			}

			.cms-media-pane {
				min-height: auto;
			}

			.cms-media-pane,
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

			.cms-media-scroll {
				padding-inline: var(--cms-space-4);
			}

			.cms-media-pane {
				border-radius: 0;

				&::after {
					display: none;
				}
			}
		}
	}
</style>
