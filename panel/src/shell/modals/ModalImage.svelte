<script lang="ts">
	import type { AssetInfo } from '$types/data';
	import type { LibraryItem } from '$shell/LibraryBrowser.svelte';

	import { cosray } from '$lib/bridge';
	import { _ } from '$lib/locale';
	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import Button from '$shell/Button.svelte';
	import LibraryBrowser from '$shell/LibraryBrowser.svelte';

	type Props = {
		close: () => void;
		add: (uid: string, info: AssetInfo) => void;
	};

	let { close, add }: Props = $props();

	let selected = $state<LibraryItem | null>(null);
	let uploading = $state(false);
	let fileInput = $state<HTMLInputElement>();

	function pick(item: LibraryItem) {
		selected = item;
	}

	function clickAdd() {
		if (selected) {
			add(selected.uid, selected);
			close();
		}
	}

	async function upload(event: Event) {
		const input = event.target as HTMLInputElement;
		const file = input.files?.[0];

		if (!file) return;

		uploading = true;
		const result = await cosray().upload('image', file);
		uploading = false;
		input.value = '';

		if (!result.ok || !result.uid) {
			cosray().toast.error(result.error ?? _('Upload failed'));

			return;
		}

		const thumbUrl = (result as { thumbUrl?: string }).thumbUrl;
		add(result.uid, {
			filename: result.filename ?? '',
			url: result.url ?? '',
			thumbUrl,
			kind: 'image',
			mime: result.mime,
			width: result.width,
			height: result.height,
		});
		close();
	}
</script>

<ModalHeader>{_('Bild einfügen')}</ModalHeader>
<ModalBody>
	<div class="cms-modal-image-body">
		<div class="cms-modal-image-upload">
			<input
				bind:this={fileInput}
				type="file"
				accept="image/*"
				class="cms-modal-image-upload-input"
				onchange={upload}
				disabled={uploading}
			/>
			<Button variant="primary" onclick={() => fileInput?.click()} disabled={uploading}>
				{uploading ? _('Lädt hoch …') : _('Bild hochladen')}
			</Button>
			<span class="cms-modal-image-upload-hint">
				{_('oder aus der Bibliothek wählen:')}
			</span>
		</div>
		<div class="cms-modal-image-library">
			<LibraryBrowser kind="image" {pick} selected={selected?.uid ?? null} />
		</div>
	</div>
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={close}>
			{_('Abbrechen')}
		</Button>
		<Button variant="primary" onclick={clickAdd} disabled={!selected}>
			{_('Bild einfügen')}
		</Button>
	</div>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-image-body {
			display: flex;
			flex-direction: column;
			gap: var(--space-4);
		}

		.cms-modal-image-upload {
			display: flex;
			align-items: center;
			gap: var(--space-3);
		}

		.cms-modal-image-upload-input {
			display: none;
		}

		.cms-modal-image-upload-hint {
			font-size: var(--font-size-sm);
			color: var(--color-neutral-500);
		}

		.cms-modal-image-library {
			max-height: 60vh;
			overflow-y: auto;
		}
	}
</style>
