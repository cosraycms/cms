<script lang="ts">
	import type { FileItem, Meta, UploadType } from '$types/data';
	import type { UploadResult } from '$lib/bridge';
	import type { Limit } from '$types/fields';
	import type { LibraryItem } from '$lib/library';

	import { mount, unmount } from 'svelte';
	import { cosray } from '$lib/bridge';
	import { registerAsset, useAssets } from '$lib/assets';
	import { __ } from '$lib/locale';
	import Icon from '$components/Icon.svelte';
	import Dialog from '$components/Dialog.svelte';
	import ModalLibrary from '$components/modals/ModalLibrary.svelte';
	import Video from '$components/Video.svelte';
	import FileList from '$components/media/FileList.svelte';
	import Gallery from '$components/media/Gallery.svelte';
	import ImageCard from '$components/media/ImageCard.svelte';
	import ImageFigure from '$components/media/ImageFigure.svelte';
	import VideoFigure from '$components/media/VideoFigure.svelte';

	type Props = {
		type: UploadType;
		name: string;
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		items: FileItem[];
		add: (item: FileItem) => void;
		limit?: Limit;
		/** The field cannot change: no bar, no drop zone, no per-item actions. */
		readonly?: boolean;
		notify?: () => void;
		// The block presentation shows a filled field as content alone —
		// a figure at block width whose per-use form goes into the
		// settings slot — and an empty one as the bar.
		presentation?: string;
		settings?: HTMLElement;
		// The gallery settings the element keeps as the field's meta.
		meta?: Meta;
		updateMeta?: (meta: Meta) => void;
	};

	let {
		type,
		name,
		translate,
		contentLocale,
		identity,
		locales,
		items = $bindable(),
		add,
		limit = { max: -1, min: 0 },
		readonly = false,
		notify = () => {},
		presentation,
		settings,
		meta,
		updateMeta,
	}: Props = $props();

	const assetStore = useAssets();

	let loading = $state(false);
	let dragging = $state(false);
	let picker: HTMLInputElement | undefined = $state();
	let allowedFiles = $derived(cosray().system().allowedFiles[type]);
	let allowed = $derived(`${__('upload:allowed-extensions')} ${allowedFiles.join(', ')}`);
	let multiple = $derived(limit.max < 1 || limit.max > 1);
	let open = $derived(limit.max < 1 || items.length < limit.max);
	let full = $derived(multiple && !open);
	let block = $derived(presentation === 'block' && (type === 'image' || type === 'video'));
	let empty = $derived(items.length === 0);
	let tally = $derived.by(() => {
		if (loading) {
			return __('upload:uploading');
		}

		if (!multiple || empty) {
			return '';
		}

		if (limit.max > 1) {
			return `${items.length} / ${limit.max}`;
		}

		if (type === 'image') {
			return items.length === 1
				? __('image:count-one', { count: 1 })
				: __('image:count-many', { count: items.length });
		}

		return __('media:file-count', { count: items.length });
	});

	function alert(body: string) {
		const handle = cosray().modal.open(
			(host) => {
				const app = mount(Dialog, {
					target: host,
					props: {
						title: __('common:error'),
						body,
						type: 'error',
						close: () => handle.close(),
					},
				});

				return () => void unmount(app);
			},
			{ owner: picker ?? document.getElementById(name) ?? undefined, size: 'compact' },
		);
	}

	function remove(index: number | null) {
		if (index === null) {
			items = [];
		} else {
			items.splice(index, 1);
			items = items;
		}
		notify();
	}

	function readItems(list: DataTransferItemList) {
		let result: File[] = [];

		for (const item of list) {
			if (item.kind === 'file') {
				const file = item.getAsFile();

				if (file) {
					result.push(file);
				}
			}
		}

		return result;
	}

	function getFilesFromDrop(event: Event) {
		const transfer = (event as DragEvent).dataTransfer;

		if (!transfer) {
			return [];
		}

		const { files, items: transferItems } = transfer;
		let result = files.length ? [...files] : readItems(transferItems);

		if (!multiple && result.length > 1) {
			alert(__('upload:single-only'));

			return [];
		}

		return result;
	}

	function getFilesFromInput(event: Event) {
		const target = event.target as HTMLInputElement;
		const files = target.files ? [...target.files] : [];

		target.value = '';

		return files;
	}

	function enforceLimit(files: File[]): File[] {
		if (limit.max < 1) {
			return files;
		}

		// A single-item field replaces what it holds.
		if (!multiple) {
			return files.slice(0, 1);
		}

		const slotsLeft = Math.max(limit.max - items.length, 0);

		if (slotsLeft === 0) {
			alert(__('upload:max-files', { max: limit.max }));

			return [];
		}

		if (files.length > slotsLeft) {
			alert(__('upload:slots-left', { count: slotsLeft }));

			return files.slice(0, slotsLeft);
		}

		return files;
	}

	// The frame's children fire their own enter/leave pairs, so the
	// drop state stays up until the pointer has left every one of them.
	let dragDepth = 0;

	function carriesFiles(event: DragEvent): boolean {
		return event.dataTransfer?.types.includes('Files') ?? false;
	}

	function dragEnter(event: DragEvent) {
		if (carriesFiles(event)) {
			event.preventDefault();
			dragDepth += 1;
			dragging = true;
		}
	}

	function dragOver(event: DragEvent) {
		if (carriesFiles(event)) {
			event.preventDefault();
		}
	}

	function dragLeave() {
		dragDepth = Math.max(0, dragDepth - 1);
		dragging = dragDepth > 0;
	}

	function drop(event: DragEvent) {
		event.preventDefault();
		void onFile(getFilesFromDrop)(event);
	}

	function openPicker() {
		picker?.click();
	}

	function replace(item: FileItem) {
		items = [item];
		notify();
	}

	async function upload(file: File) {
		return await cosray().upload(type, file);
	}

	function uploadError(item: UploadResult) {
		cosray().toast.error(
			__('upload:file-label') + ' ' + (item.filename ?? '') + ': ' + (item.error ?? ''),
		);
	}

	// Fresh items carry only the uid — per-use meta stays absent until
	// the editor actually fills it, so catalog defaults keep applying.
	function uploaded(item: UploadResult) {
		if (!item.ok || !item.uid) {
			uploadError(item);

			return;
		}

		registerAsset(assetStore, item.uid, {
			filename: item.filename ?? '',
			url: item.url ?? '',
			thumbUrl: item.thumbUrl,
			previewUrl: item.previewUrl,
			kind: type,
			mime: item.mime,
			bytes: item.bytes,
			width: item.width,
			height: item.height,
		});

		add({ uid: item.uid });
	}

	function onFile(getFilesFunction: (event: Event) => File[]) {
		return async (event: Event) => {
			dragDepth = 0;
			dragging = false;
			let files = enforceLimit(getFilesFunction(event));

			if (files.length > 0) {
				loading = true;

				let responses = (await Promise.all(files.map(upload))).filter(
					(item): item is UploadResult => item !== undefined,
				);

				responses.forEach(uploaded);
			}

			loading = false;
			notify();
		};
	}

	function pickFromLibrary(item: LibraryItem) {
		if (full) {
			alert(__('upload:max-files', { max: limit.max }));

			return;
		}

		registerAsset(assetStore, item.uid, item);

		add({ uid: item.uid });
		notify();
	}

	function openLibrary() {
		const handle = cosray().modal.open(
			(host) => {
				const app = mount(ModalLibrary, {
					target: host,
					props: {
						kind: type,
						close: () => handle.close(),
						pick: (item: LibraryItem) => {
							handle.close();
							pickFromLibrary(item);
						},
					},
				});

				return () => void unmount(app);
			},
			{ owner: picker ?? document.getElementById(name) ?? undefined },
		);
	}
</script>

<div
	class="cms-media-field cms-dropzone"
	class:is-block={block}
	class:is-empty={empty}
	class:is-dragging={dragging}
	class:is-readonly={readonly}
	role="group"
	ondragenter={readonly ? undefined : dragEnter}
	ondragover={readonly ? undefined : dragOver}
	ondragleave={readonly ? undefined : dragLeave}
	ondrop={readonly ? undefined : drop}
>
	<!-- A filled single field replaces through its own menu instead. -->
	{#if !readonly && (empty || (multiple && !block))}
		<div class="bar">
			<button
				type="button"
				class="browse cms-button secondary small"
				disabled={full}
				onclick={openLibrary}
			>
				<Icon name="folder2-open" />
				{__('media:browse')}
			</button>
			<span class="hint">
				<Icon name="cloud-upload" />
				<span>
					{__('upload:drop-here')}
					<button type="button" class="choose" title={allowed} disabled={full} onclick={openPicker}
						>{multiple ? __('upload:choose-files') : __('upload:choose-file')}</button
					>.
				</span>
			</span>
			{#if tally}
				<span class="tally">{tally}</span>
			{/if}
		</div>
	{/if}
	{#if readonly && empty}
		<div class="none">{multiple ? __('media:empty-many') : __('media:empty-one')}</div>
	{/if}
	<!-- A block gallery mounts while empty too: its settings live in the slot. -->
	{#if !empty || (block && multiple)}
		<div class="body">
			{#if block}
				{#if type === 'video'}
					<VideoFigure
						item={items[0]}
						{loading}
						{readonly}
						{translate}
						{contentLocale}
						{identity}
						{locales}
						{settings}
						update={replace}
						remove={() => remove(null)}
						upload={openPicker}
						library={openLibrary}
					/>
				{:else if multiple}
					<Gallery
						bind:items
						{loading}
						{translate}
						{contentLocale}
						{identity}
						{locales}
						{open}
						{readonly}
						{notify}
						presentation="block"
						{settings}
						{meta}
						{updateMeta}
						remove={(index) => remove(index)}
						upload={openPicker}
						library={openLibrary}
					/>
				{:else}
					<ImageFigure
						item={items[0]}
						{loading}
						{readonly}
						{translate}
						{contentLocale}
						{identity}
						{locales}
						{settings}
						update={replace}
						remove={() => remove(null)}
						upload={openPicker}
						library={openLibrary}
					/>
				{/if}
			{:else if type === 'image'}
				{#if multiple}
					<Gallery
						bind:items
						{loading}
						{translate}
						{contentLocale}
						{identity}
						{locales}
						{open}
						{readonly}
						{notify}
						remove={(index) => remove(index)}
						upload={openPicker}
						library={openLibrary}
					/>
				{:else}
					<ImageCard
						item={items[0]}
						{loading}
						{readonly}
						{translate}
						{contentLocale}
						{identity}
						{locales}
						update={replace}
						remove={() => remove(null)}
						upload={openPicker}
						library={openLibrary}
					/>
				{/if}
			{:else}
				{#if type === 'video'}
					<Video file={items[0]} />
				{/if}
				<FileList
					bind:items
					kind={type === 'video' ? 'video' : 'file'}
					{loading}
					{translate}
					{contentLocale}
					{identity}
					{locales}
					{readonly}
					{notify}
					remove={(index) => remove(index)}
					replace={multiple ? undefined : { upload: openPicker, library: openLibrary }}
				/>
			{/if}
		</div>
	{/if}
	{#if dragging}
		<div class="drop" aria-hidden="true">
			<Icon name="cloud-upload" />
			{!multiple && !empty ? __('upload:drop-to-replace') : __('media:drop-to-upload')}
		</div>
	{/if}
	{#if !readonly}
		<input
			bind:this={picker}
			type="file"
			id={name}
			{multiple}
			accept={allowedFiles.map((suffix) => '.' + suffix).join(',')}
			oninput={onFile(getFilesFromInput)}
		/>
	{/if}
</div>
