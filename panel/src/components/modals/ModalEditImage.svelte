<script lang="ts">
	import type { FileItem } from '$types/data';
	import { untrack } from 'svelte';
	import { ModalHeader, ModalBody, ModalFooter } from '$components/modal';
	import { __ } from '$lib/locale';
	import Button from '$components/Button.svelte';
	import ContentLocales from '$components/ContentLocales.svelte';
	import MetaFields from '$components/media/MetaFields.svelte';

	type Props = {
		close: () => void;
		// Receives the draft with pruned meta, so empty texts never shadow
		// the asset's catalog defaults.
		apply: (asset: FileItem) => void;
		asset: FileItem;
		// A video edits its caption, a file its title.
		kind: 'video' | 'file';
		translate: boolean;
		contentLocale: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
	};

	let { close, apply, asset, kind, translate, contentLocale, locales }: Props = $props();
	let draft: FileItem = $state(untrack(() => $state.snapshot(asset)));
</script>

<ModalHeader>{kind === 'video' ? __('image:caption') : __('media:file-details')}</ModalHeader>
<ModalBody>
	<div class="cms-modal-edit-image-fields">
		{#if translate && locales && locales.all.length > 1}
			<div class="cms-modal-edit-image-locales">
				<ContentLocales locales={locales.all} locale={contentLocale} />
			</div>
		{/if}
		<MetaFields
			item={draft}
			{kind}
			{translate}
			{contentLocale}
			{locales}
			update={(next) => (draft = next)}
		/>
	</div>
</ModalBody>
<ModalFooter>
	<Button variant="secondary" onclick={close}>
		{__('common:cancel')}
	</Button>
	<Button variant="primary" onclick={() => apply(draft)}>
		{__('common:apply')}
	</Button>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-edit-image-fields {
			margin-bottom: var(--cms-space-8);
		}

		.cms-modal-edit-image-locales {
			margin-bottom: var(--cms-space-4);
		}
	}
</style>
