<script lang="ts">
	import type { Snippet } from 'svelte';
	import { ZXX, type BlockText } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import RichTextEditor from '$shell/richtext/RichTextEditor.svelte';

	type Props = {
		field: BlocksField;
		item: BlockText;
		index: number;
		children: Snippet<[{ edit: () => void }]>;
	};

	let { field, item = $bindable(), index, children }: Props = $props();

	let showSettings = $state(false);
</script>

<div class="block-cell-header">
	{@render children({ edit: () => (showSettings = !showSettings) })}
</div>
<div class="block-cell-body">
	{#if showSettings}
		<div>Keine Einstellungsmöglichkeiten vorhanden</div>
	{:else}
		<RichTextEditor required={false} name={field.name + '_' + index} bind:value={item.value[ZXX]} />
	{/if}
</div>
