<script lang="ts">
	import { preventDefault } from 'svelte/legacy';

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
	import Message from '$components/Message.svelte';
	import MediaList from '$components/MediaList.svelte';
	import ModalLibrary from '$components/modals/ModalLibrary.svelte';
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
		required?: boolean;
		/** The field cannot change: no picker, no drop zone, no library. */
		readonly?: boolean;
		disabled?: boolean;
		disabledMsg?: string;
		callback?: (() => void) | null;
		inline?: boolean;
		notify?: () => void;
		// The block presentation renders content only: a figure at block
		// width whose per-use form goes into the settings slot when given.
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
		required = false,
		readonly = false,
		disabled = false,
		disabledMsg = '',
		callback = null,
		inline = false,
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
	let allowedExtensions = $derived(allowedFiles.join(', '));
	let multiple = $derived(limit.max < 1 || limit.max > 1);
	let open = $derived(!items || limit.max < 1 || items.length < limit.max);
	let block = $derived(presentation === 'block' && (type === 'image' || type === 'video'));

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

	function getFilesFromDrop(event: DragEvent | Event) {
		if (!(event instanceof DragEvent) || !event.dataTransfer) {
			return [];
		}

		const { files, items: transferItems } = event.dataTransfer;
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
		// unlimited
		if (limit.max < 1) {
			return files;
		}

		// A single-item field replaces what it holds.
		if (!multiple) {
			return files.slice(0, 1);
		}

		const slotsLeft = Math.max(limit.max - (items?.length ?? 0), 0);

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

	function startDragging() {
		dragging = true;
	}

	function stopDragging() {
		dragDepth = 0;
		dragging = false;
	}

	// The card's children fire their own enter/leave pairs, so the
	// overlay stays up until the pointer has left every one of them.
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

	function onFile(getFilesFunction: (event: DragEvent | Event) => File[]) {
		return async (event: Event) => {
			stopDragging();
			let files = enforceLimit(getFilesFunction(event));

			if (files.length > 0) {
				loading = true;

				let responses = (await Promise.all(files.map(upload))).filter(
					(item): item is UploadResult => item !== undefined,
				);

				responses.forEach(uploaded);

				if (items && callback) {
					callback();
				}
			}

			loading = false;
			notify();
		};
	}

	function pickFromLibrary(item: LibraryItem) {
		if (multiple && limit.max >= 1 && (items?.length ?? 0) >= limit.max) {
			alert(__('upload:max-files', { max: limit.max }));

			return;
		}

		registerAsset(assetStore, item.uid, item);

		add({ uid: item.uid });
		notify();

		if (callback) {
			callback();
		}
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

{#if disabled}
	{#if disabledMsg}
		<Message type="warning" text={disabledMsg} />
	{:else}
		<Message type="warning" text={__('upload:save-first')} />
	{/if}
{:else if block}
	<div
		class="cms-media-block"
		class:is-dragging={dragging}
		role="group"
		ondragenter={readonly ? undefined : dragEnter}
		ondragover={readonly ? undefined : dragOver}
		ondragleave={readonly ? undefined : dragLeave}
		ondrop={readonly ? undefined : drop}
	>
		{#if type === 'video'}
			<VideoFigure
				item={items?.[0] ?? null}
				{loading}
				{readonly}
				{translate}
				{contentLocale}
				{identity}
				{locales}
				{settings}
				allowed="{__('upload:allowed-extensions')} {allowedExtensions}"
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
				item={items?.[0] ?? null}
				{loading}
				{readonly}
				{translate}
				{contentLocale}
				{identity}
				{locales}
				{settings}
				allowed="{__('upload:allowed-extensions')} {allowedExtensions}"
				update={replace}
				remove={() => remove(null)}
				upload={openPicker}
				library={openLibrary}
			/>
		{/if}
		{#if dragging}
			<div class="drop" aria-hidden="true">
				<span>
					<Icon name="cloud-upload" />
					{items?.length && !multiple ? __('upload:drop-to-replace') : __('upload:drop-to-add')}
				</span>
			</div>
		{/if}
		<input
			bind:this={picker}
			type="file"
			id={name}
			{multiple}
			accept={allowedFiles.map((suffix) => '.' + suffix).join(',')}
			oninput={onFile(getFilesFromInput)}
		/>
	</div>
{:else if type === 'image'}
	<div class="cms-media-field" class:required class:is-dragging={dragging}>
		<div
			class="card"
			role="group"
			ondragenter={readonly ? undefined : dragEnter}
			ondragover={readonly ? undefined : dragOver}
			ondragleave={readonly ? undefined : dragLeave}
			ondrop={readonly ? undefined : drop}
		>
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
					item={items?.[0] ?? null}
					{loading}
					{readonly}
					{translate}
					{contentLocale}
					{identity}
					{locales}
					allowed="{__('upload:allowed-extensions')} {allowedExtensions}"
					update={replace}
					remove={() => remove(null)}
					upload={openPicker}
					library={openLibrary}
				/>
			{/if}
			{#if dragging}
				<div class="drop" aria-hidden="true">
					<span>
						<Icon name="cloud-upload" />
						{multiple ? __('upload:drop-to-add') : __('upload:drop-to-replace')}
					</span>
				</div>
			{/if}
			<input
				bind:this={picker}
				type="file"
				id={name}
				{multiple}
				accept={allowedFiles.map((suffix) => '.' + suffix).join(',')}
				oninput={onFile(getFilesFromInput)}
			/>
		</div>
		{#if multiple}
			<div class="allowed">{__('upload:allowed-extensions')} {allowedExtensions}</div>
		{/if}
	</div>
{:else}
	<div
		class="upload upload-{type}"
		class:required
		class:upload-multiple={multiple}
		class:upload-inline={inline}
	>
		<MediaList
			bind:items
			{multiple}
			{type}
			{remove}
			{loading}
			{translate}
			{contentLocale}
			{identity}
			{locales}
			{readonly}
			{notify}
		/>
		{#if open && !readonly}
			<label
				class="dragdrop"
				for={name}
				ondrop={preventDefault(onFile(getFilesFromDrop))}
				ondragover={preventDefault(startDragging)}
				ondragleave={preventDefault(stopDragging)}
			>
				<div class="cms-field-label upload-drop-label">
					<span class="upload-drop-icon"><Icon name="cloud-upload" /></span>
					{__('upload:dropzone')}
					<u>{__('common:select')}</u>
				</div>
				<div class="file-extensions">
					{__('upload:allowed-extensions')}
					{allowedExtensions}
				</div>
				<button type="button" class="library-button" onclick={preventDefault(openLibrary)}>
					{__('media:choose-from-library')}
				</button>
				<input type="file" id={name} {multiple} oninput={onFile(getFilesFromInput)} />
			</label>
		{/if}
	</div>
{/if}

<style>
	@layer panel {
		.cms-media-field {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-2);
			width: 100%;

			& .card {
				position: relative;
				border: 1px solid var(--cms-color-border-strong);
				border-radius: var(--cms-radius-md);
				background: var(--cms-color-surface);
			}

			&.is-dragging .card {
				border-color: var(--cms-color-accent);
			}

			& .drop {
				position: absolute;
				inset: 0;
				z-index: 1;
				display: flex;
				align-items: center;
				justify-content: center;
				border-radius: inherit;
				background: color-mix(in srgb, var(--cms-color-surface) 92%, transparent);
				box-shadow: inset 0 0 0 2px var(--cms-color-accent);
				font-size: var(--cms-font-size-sm);
				font-weight: 600;
				pointer-events: none;

				& span {
					display: inline-flex;
					align-items: center;
					gap: var(--cms-space-2);
				}

				& :global(svg) {
					width: var(--cms-space-4);
					height: var(--cms-space-4);
				}
			}

			& .allowed {
				font-size: var(--cms-font-size-xs);
				line-height: 1.5;
				color: var(--cms-color-text-faint);
			}

			& input[type='file'] {
				position: absolute;
				width: 1px;
				height: 1px;
				overflow: hidden;
				clip: rect(1px, 1px, 1px, 1px);
				white-space: nowrap;
			}
		}

		/* The block presentation: no frame of its own, the figure is the content. */
		.cms-media-block {
			position: relative;

			& .drop {
				position: absolute;
				inset: 0;
				z-index: 1;
				display: flex;
				align-items: center;
				justify-content: center;
				border-radius: var(--cms-radius-md);
				background: color-mix(in srgb, var(--cms-color-surface) 92%, transparent);
				box-shadow: inset 0 0 0 2px var(--cms-color-accent);
				font-size: var(--cms-font-size-sm);
				font-weight: 600;
				pointer-events: none;

				& span {
					display: inline-flex;
					align-items: center;
					gap: var(--cms-space-2);
				}

				& :global(svg) {
					width: var(--cms-space-4);
					height: var(--cms-space-4);
				}
			}

			& input[type='file'] {
				position: absolute;
				width: 1px;
				height: 1px;
				overflow: hidden;
				clip: rect(1px, 1px, 1px, 1px);
				white-space: nowrap;
			}
		}

		.upload {
			display: flex;
			width: 100%;
			height: 100%;
			flex-direction: column;

			&.upload-inline {
				margin-top: var(--cms-space-6);
			}

			&.upload-multiple {
				flex-direction: column;
			}
		}

		@media (min-width: 768px) {
			.upload {
				flex-direction: row;
			}
		}

		.dragdrop {
			/* Containing block for the visually hidden file input below —
			   without it the absolute input escapes the editor's scroll
			   pane, grows the document, and label focus scrolls the page
			   away. */
			position: relative;
			display: flex;
			flex: 1 1 auto;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			border: 2px dashed var(--cms-color-border-strong);
			border-radius: var(--cms-radius-md);
			background-color: var(--cms-color-surface-sunken);
			padding: var(--cms-space-4) var(--cms-space-2);
			text-align: center;
			vertical-align: middle;
		}

		.dragdrop.dragging {
			border-color: var(--cms-color-accent);
			background-color: var(--cms-color-accent-surface);
		}

		.upload-drop-label {
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: center;
			gap: var(--cms-space-2);
			color: var(--cms-color-text-muted);
		}

		.upload-drop-icon {
			display: inline-block;
			width: var(--cms-space-6);
			height: var(--cms-space-6);
		}

		.upload input {
			position: absolute;
			height: 1px;
			width: 1px;
			overflow: hidden;
			clip: rect(1px 1px 1px 1px);
			clip: rect(1px, 1px, 1px, 1px);
			white-space: nowrap;
		}

		.dragdrop:hover {
			cursor: pointer;
		}

		:global(.dragdrop > .upload-drop-label svg) {
			display: inline;
			margin-bottom: var(--cms-space-2);
		}
		:global(.dragdrop > .upload-drop-label u) {
			color: var(--cms-color-accent);
		}

		.dragdrop > div.file-extensions {
			font-weight: normal;
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-text-faint);
			margin-top: var(--cms-space-1);
		}

		.library-button {
			margin-top: var(--cms-space-3);
			border: 1px solid var(--cms-color-border-strong);
			border-radius: var(--cms-radius-md);
			background-color: var(--cms-color-surface);
			padding: var(--cms-space-1) var(--cms-space-3);
			font-size: var(--cms-font-size-sm);
			color: var(--cms-color-text-muted);
			cursor: pointer;
		}

		.library-button:hover {
			border-color: var(--cms-color-accent);
			color: var(--cms-color-accent);
		}
	}
</style>
