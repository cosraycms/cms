<script lang="ts">
	import { __ } from '$lib/locale';
	import type { Snippet } from 'svelte';
	import type { BlockImage } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { useNotify } from '../notify';
	import Upload from '$shell/Upload.svelte';

	type Props = {
		field: BlocksField;
		item: BlockImage;
		node: string;
		index: number;
		children: Snippet<[{ edit: () => void }]>;
	};

	let { field, item = $bindable(), node, index, children }: Props = $props();
	const notify = useNotify();

	let showSettings = $state(false);
	const SINGLE_LIMIT = { min: 0, max: 1 };
</script>

<div class="block-cell-header">
	{@render children({ edit: () => (showSettings = !showSettings) })}
</div>
<div class="block-cell-body">
	{#if showSettings}
		<div>{__('block:no-settings')}</div>
	{:else}
		<Upload
			type="video"
			limit={SINGLE_LIMIT}
			{notify}
			name={field.name + '_' + index}
			translate={false}
			bind:items={item.value}
		/>
	{/if}
</div>
