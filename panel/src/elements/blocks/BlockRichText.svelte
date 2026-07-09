<script lang="ts">
	import { __ } from '$lib/locale';
	import type { Snippet } from 'svelte';
	import type { AssetInfo, RichtextDoc } from '$types/data';
	import { ZXX, type BlockRichText } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { registerAsset, useAssets } from '$lib/assets';
	import { FORMAT, VERSION, htmlToDoc } from '$shell/richtext/format';
	import { useNotify } from '../notify';
	import RichTextEditor from '$shell/richtext/RichTextEditor.svelte';

	type Props = {
		field: BlocksField;
		item: BlockRichText;
		index: number;
		children: Snippet<[{ edit: () => void }]>;
	};

	let { field, item = $bindable(), index, children }: Props = $props();
	const notify = useNotify();
	const assetStore = useAssets();

	let showSettings = $state(false);

	// Legacy HTML blocks convert on mount; the envelope keys stamp the
	// block so the writer-strict save accepts it.
	if (item.format !== FORMAT) {
		for (const [id, raw] of Object.entries(item.value ?? {})) {
			if (typeof raw === 'string') {
				item.value[id] = htmlToDoc(raw);
			}
		}

		item.format = FORMAT;
		item.version = VERSION;
	}

	function stamp() {
		item.format = FORMAT;
		item.version = VERSION;
		notify();
	}

	function assetUrl(uid: string): string | null {
		const info = $assetStore[uid];

		return info?.thumbUrl ?? info?.url ?? null;
	}

	function onAsset(uid: string, info: AssetInfo) {
		registerAsset(assetStore, uid, info);
	}
</script>

<div class="block-cell-header">
	{@render children({ edit: () => (showSettings = !showSettings) })}
</div>
<div class="block-cell-body">
	{#if showSettings}
		<div>{__('block:no-settings')}</div>
	{:else}
		<RichTextEditor
			required={false}
			name={field.name + '_' + index}
			classes={field.richtextClasses ?? {}}
			styles={field.richtextStyles ?? {}}
			{assetUrl}
			{onAsset}
			bind:value={
				() => (item.value[ZXX] as RichtextDoc | null) ?? null, (doc) => (item.value[ZXX] = doc)
			}
			notify={stamp}
		/>
	{/if}
</div>
