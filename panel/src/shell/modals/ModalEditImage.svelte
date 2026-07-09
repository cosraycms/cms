<script lang="ts">
	import type { FileItem } from '$types/data';
	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import { __ } from '$lib/locale';
	import Button from '$shell/Button.svelte';
	import Input from '$shell/controls/Input.svelte';

	type Props = {
		close: () => void;
		// The applying caller prunes empty meta before persisting, so the
		// editing scaffold below never shadows catalog defaults.
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

<ModalHeader>{__('image:title-and-alt')}</ModalHeader>
<ModalBody>
	<div class="cms-modal-edit-image-fields">
		<Input bind:value={meta.title} label={__('common:title')} id="edit_image_title" {translate} />
		{#if hasAlt}
			<Input
				bind:value={meta.alt}
				label={__('image:alt-text')}
				id="edit_image_alt"
				{translate}
				description={__('image:alt-text-help')}
			/>
		{/if}
	</div>
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={close}>
			{__('common:cancel')}
		</Button>
		<Button variant="primary" onclick={() => apply(asset)}>
			{__('common:apply')}
		</Button>
	</div>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-edit-image-fields {
			display: flex;
			flex-direction: column;
			gap: var(--space-4);
			margin-bottom: var(--space-8);
		}
	}
</style>
