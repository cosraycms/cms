<script lang="ts">
	import type { Snippet } from 'svelte';
	import { ZXX, type BlockText } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { useNotify } from '../notify';

	type Props = {
		field: BlocksField;
		item: BlockText;
		index: number;
		children: Snippet<[{ edit: () => void }]>;
	};

	let { field, item = $bindable(), index, children }: Props = $props();
	const notify = useNotify();
	let showSettings = $state(false);

	function oninput() {
		notify();
	}
</script>

<div class="block-cell-header">
	{@render children({ edit: () => (showSettings = !showSettings) })}
</div>
<div class="block-cell-body">
	{#if showSettings}
		<div>Keine Einstellungsmöglichkeiten vorhanden</div>
	{:else}
		<textarea
			class="iframe"
			rows="5"
			id={`${field.name}_${index}`}
			name={`${field.name}_${index}`}
			bind:value={item.value[ZXX]}
			{oninput}
		>
		</textarea>
	{/if}
</div>
