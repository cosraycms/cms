<script lang="ts">
	import type { FileItem } from '$types/data';
	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import { _ } from '$lib/locale';
	import Button from '$shell/Button.svelte';
	import Input from '$shell/controls/Input.svelte';

	type Props = {
		close: () => void;
		apply: (asset: FileItem) => void;
		asset: FileItem;
		translate: boolean;
		hasAlt: boolean;
	};

	let { close, apply, asset = $bindable(), translate, hasAlt }: Props = $props();
	asset.meta ??= {};
	asset.meta.title ??= { zxx: '' };
	asset.meta.alt ??= { zxx: '' };
	let meta = $derived(asset.meta);
</script>

<ModalHeader>{_('Bildtitel und Alt-Text')}</ModalHeader>
<ModalBody>
	<div class="cms-modal-edit-image-fields">
		<Input bind:value={meta.title} label={_('Titel')} id="edit_image_title" {translate} />
		{#if hasAlt}
			<Input
				bind:value={meta.alt}
				label={_('Alt-Text')}
				id="edit_image_alt"
				{translate}
				description={_(
					'Ein Alt-Text ist eine kurze Bildbeschreibung oder eine sprachliche Übersetzung eines visuellen Inhalts im Internet, die blinden Benutzern von Hilfsmitteln wie Screen- readern anstelle des Bildes vorgelesen wird. Suchmaschinen verwenden diesen Text ebenfalls.',
				)}
			/>
		{/if}
	</div>
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button class="danger" onclick={close}>
			{_('Abbrechen')}
		</Button>
		<Button class="primary" onclick={() => apply(asset)}>
			{_('Übernehmen')}
		</Button>
	</div>
</ModalFooter>

<style>
	.cms-modal-edit-image-fields {
		display: flex;
		flex-direction: column;
		gap: var(--cms-space-4);
		margin-bottom: var(--cms-space-8);
	}
</style>
