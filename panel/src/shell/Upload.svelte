<script lang="ts">
	import { preventDefault } from 'svelte/legacy';

	import type { FileItem, UploadType } from '$types/data';
	import type { UploadResult } from '$lib/bridge';
	import type { Limit } from '$types/fields';
	import type { LibraryItem } from '$shell/LibraryBrowser.svelte';

	import { mount, unmount } from 'svelte';
	import { cosray } from '$lib/bridge';
	import { registerAsset, useAssets } from '$lib/assets';
	import { _ } from '$lib/locale';
	import IcoUpload from '$shell/icons/IcoUpload.svelte';
	import Dialog from '$shell/Dialog.svelte';
	import Message from '$shell/Message.svelte';
	import MediaList from '$shell/MediaList.svelte';
	import ModalLibrary from '$shell/modals/ModalLibrary.svelte';

	type Props = {
		type: UploadType;
		name: string;
		translate: boolean;
		items: FileItem[];
		limit?: Limit;
		required?: boolean;
		disabled?: boolean;
		disabledMsg?: string;
		callback?: (() => void) | null;
		inline?: boolean;
		notify?: () => void;
	};

	let {
		type,
		name,
		translate,
		items = $bindable(),
		limit = { max: -1, min: 0 },
		required = false,
		disabled = false,
		disabledMsg = '',
		callback = null,
		inline = false,
		notify = () => {},
	}: Props = $props();

	const assetStore = useAssets();

	let loading = $state(false);
	let dragging = $state(false);
	let allowedExtensions = $derived(cosray().system().allowedFiles[type].join(', '));
	let multiple = $derived(limit.max < 1 || limit.max > 1);

	function alert(body: string) {
		const handle = cosray().modal.open((host) => {
			const app = mount(Dialog, {
				target: host,
				props: {
					title: _('Fehler'),
					body,
					type: 'error',
					close: () => handle.close(),
				},
			});

			return () => void unmount(app);
		});
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
			alert(_('In diesem Feld ist nur eine einzelne Datei erlaubt.'));

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

		const slotsLeft = Math.max(limit.max - (items?.length ?? 0), 0);

		if (slotsLeft === 0) {
			alert(_('In diesem Feld sind maximal') + ' ' + limit.max + ' ' + _('Dateien erlaubt.'));

			return [];
		}

		if (files.length > slotsLeft) {
			alert(_('Es können nur noch') + ' ' + slotsLeft + ' ' + _('Datei(en) hinzugefügt werden.'));

			return files.slice(0, slotsLeft);
		}

		return files;
	}

	function startDragging() {
		dragging = true;
	}

	function stopDragging() {
		dragging = false;
	}

	async function upload(file: File) {
		return await cosray().upload(type, file);
	}

	function uploadError(item: UploadResult) {
		cosray().toast.error(_('Datei:') + ' ' + (item.filename ?? '') + ': ' + (item.error ?? ''));
	}

	// Fresh items carry only the uid — per-use meta stays absent until
	// the editor actually fills it, so catalog defaults keep applying.
	function add(item: UploadResult) {
		if (!item.ok || !item.uid) {
			uploadError(item);

			return;
		}

		registerAsset(assetStore, item.uid, {
			filename: item.filename ?? '',
			url: item.url ?? '',
			kind: type,
			mime: item.mime,
			width: item.width,
			height: item.height,
		});

		if (multiple) {
			items.push({ uid: item.uid });
			items = [...items];
		} else {
			items = [{ uid: item.uid }];
		}
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

				responses.map(add);

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
			alert(_('In diesem Feld sind maximal') + ' ' + limit.max + ' ' + _('Dateien erlaubt.'));

			return;
		}

		registerAsset(assetStore, item.uid, item);

		if (multiple) {
			items.push({ uid: item.uid });
			items = [...items];
		} else {
			items = [{ uid: item.uid }];
		}

		notify();

		if (callback) {
			callback();
		}
	}

	function openLibrary() {
		const handle = cosray().modal.open((host) => {
			const app = mount(ModalLibrary, {
				target: host,
				props: {
					kind: type,
					close: () => handle.close(),
					pick: (item: LibraryItem) => {
						pickFromLibrary(item);
						handle.close();
					},
				},
			});

			return () => void unmount(app);
		});
	}
</script>

{#if disabled}
	{#if disabledMsg}
		<Message type="warning" text={disabledMsg} />
	{:else}
		<Message type="warning" text={_('-warning-save-to-upload-')} />
	{/if}
{:else}
	<div
		class="upload upload-{type}"
		class:required
		class:upload-multiple={multiple}
		class:upload-inline={inline}
	>
		<MediaList bind:items {multiple} {type} {remove} {loading} {translate} {notify} />
		{#if !items || limit.max < 1 || items.length < limit.max}
			<label
				class="dragdrop"
				class:dragging
				class:image={type === 'image'}
				for={name}
				ondrop={preventDefault(onFile(getFilesFromDrop))}
				ondragover={preventDefault(startDragging)}
				ondragleave={preventDefault(stopDragging)}
			>
				<div class="cms-field-label upload-drop-label">
					<span class="upload-drop-icon"><IcoUpload /></span>
					{_('Neue Dateien per Drag and Drop hier einfügen oder')}
					<u>{_('auswählen')}</u>
				</div>
				<div class="file-extensions">
					Erlaubte Dateiendungen: {allowedExtensions}
				</div>
				<button type="button" class="library-button" onclick={preventDefault(openLibrary)}>
					{_('Aus Bibliothek wählen')}
				</button>
				<input type="file" id={name} {multiple} oninput={onFile(getFilesFromInput)} />
			</label>
		{/if}
	</div>
{/if}

<style>
	@layer panel {
		.upload {
			display: flex;
			width: 100%;
			height: 100%;
			flex-direction: column;

			&.upload-inline {
				margin-top: var(--space-6);
			}

			&.upload-multiple {
				flex-direction: column;
			}

			&.required .dragdrop {
				border-left-width: 4px;
				border-left-color: var(--color-danger);
				border-left-style: solid;
			}
		}

		@media (min-width: 768px) {
			.upload {
				flex-direction: row;
			}
		}

		.dragdrop {
			display: flex;
			flex: 1 1 auto;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			border: 2px dashed var(--color-neutral-300);
			border-radius: var(--radius-md);
			background-color: var(--color-neutral-100);
			padding: var(--space-4) var(--space-2);
			text-align: center;
			vertical-align: middle;
		}

		.dragdrop.dragging {
			border-color: var(--color-info);
			background-color: var(--color-info-surface);
		}

		.upload-drop-label {
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: center;
			gap: var(--space-2);
			color: var(--color-neutral-600);
		}

		.upload-drop-icon {
			display: inline-block;
			width: var(--space-6);
			height: var(--space-6);
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
			margin-bottom: var(--space-2);
		}
		:global(.dragdrop > .upload-drop-label u) {
			color: var(--color-info);
		}

		.dragdrop > div.file-extensions {
			font-weight: normal;
			font-size: var(--font-size-xs);
			color: var(--color-neutral-400);
			margin-top: var(--space-1);
		}

		.library-button {
			margin-top: var(--space-3);
			border: 1px solid var(--color-neutral-300);
			border-radius: var(--radius-md);
			background-color: var(--color-white);
			padding: var(--space-1) var(--space-3);
			font-size: var(--font-size-sm);
			color: var(--color-neutral-600);
			cursor: pointer;
		}

		.library-button:hover {
			border-color: var(--color-info);
			color: var(--color-info);
		}

		@media (min-width: 768px) {
			:global(.upload-image .preview) {
				width: var(--fraction-2-5);
			}
		}
	}
</style>
