<script lang="ts">
	import { system, systemLocale } from '$lib/sys';
	import Field from '$shell/Field.svelte';
	import LabelDiv from '$shell/LabelDiv.svelte';
	import BlocksPanel from './BlocksPanel.svelte';
	import type { BlockItem, BlocksData } from '$types/data';
	import type { BlocksField } from '$types/fields';

	type Props = {
		field: BlocksField;
		data: BlocksData;
		node: string;
	};

	let { field, data = $bindable(), node }: Props = $props();

	let lang = $state(systemLocale($system));

	function blockItems(lang?: string): BlockItem[] {
		const value = data.value;

		if (Array.isArray(value)) {
			return value;
		}

		if (lang === undefined) {
			return [];
		}

		value[lang] ??= [];

		return value[lang];
	}
</script>

<Field {field}>
	<LabelDiv
		translate={field.translate}
		bind:lang>
		{field.label}
	</LabelDiv>
	<div class="cms-field-content">
		{#if data.value}
			{#if field.translate}
				{#each $system.locales as locale}
					{#if locale.id === lang}
						<BlocksPanel
							data={blockItems(lang)}
							{field}
							{node} />
					{/if}
				{/each}
			{:else}
				<BlocksPanel
					data={blockItems()}
					{field}
					{node} />
			{/if}
		{/if}
	</div>
</Field>
