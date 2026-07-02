<script lang="ts">
	import type { Snippet } from 'svelte';
	import type { BlockImage } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { setDirty } from '$lib/state';
	import Upload from '$shell/Upload.svelte';

	type Props = {
		field: BlocksField;
		item: BlockImage;
		node: string;
		index: number;
		children: Snippet<[{ edit: () => void }]>;
	};

	let { field, item = $bindable(), node, index, children }: Props = $props();

	let showSettings = $state(false);
	const SINGLE_LIMIT = { min: 0, max: 1 };
</script>

<div class="block-cell-header">
	{@render children({ edit: () => (showSettings = !showSettings) })}
</div>
<div class="block-cell-body">
	{#if showSettings}
		<div>Keine Einstellungsmöglichkeiten vorhanden</div>
	{:else}
		<Upload
			type="video"
			limit={SINGLE_LIMIT}
			{node}
			notify={setDirty}
			name={field.name + '_' + index}
			translate={false}
			bind:assets={item.value}
		/>
	{/if}
</div>
