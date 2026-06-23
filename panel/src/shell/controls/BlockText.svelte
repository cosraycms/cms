<script lang="ts">
	import type { Snippet } from 'svelte';
	import { ZXX, type BlockText } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { setDirty } from '$lib/state';

	type Props = {
		field: BlocksField;
		item: BlockText;
		index: number;
		children: Snippet<[{ edit: () => void }]>;
	};

	let { field, item = $bindable(), index, children }: Props = $props();
	let showSettings = $state(false);

	function oninput() {
		setDirty();
	}
</script>

<div class="block-cell-header">
	{@render children({ edit: () => (showSettings = !showSettings) })}
</div>
<div class="block-cell-body cms-blocks-text-body">
	{#if showSettings}
		<div>Keine Einstellungsmöglichkeiten vorhanden</div>
	{:else}
		<textarea name={field.name + '_' + index} bind:value={item.value[ZXX]} {oninput}> </textarea>
	{/if}
</div>

<style lang="postcss">
	.cms-blocks-text-body {
		flex-grow: 1;
	}

	textarea {
		height: 100%;
	}
</style>
