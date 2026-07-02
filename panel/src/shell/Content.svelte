<script lang="ts">
	import type { Node } from '$types/data';
	import type { Field } from '$types/fields';
	import { setDirty } from '$lib/state';
	import Control from '$shell/Control.svelte';

	type Props = {
		node: Node;
		fields: Field[];
		visibleFields?: string[];
	};

	let { node = $bindable(), fields = $bindable(), visibleFields = [] }: Props = $props();

	function fieldSpan(value: number | null, type: 'row' | 'col') {
		if (value) {
			if (value > 100 || value <= 0) value = 100;

			return `span ${value} / span ${value}`;
		}

		if (type === 'col') {
			return 'span 100 / span 100';
		}

		return 'span 1 / span 1';
	}

	function shouldAddField(fieldName: string): boolean {
		if (visibleFields.length > 0) {
			return visibleFields.indexOf(fieldName) > -1;
		}

		return true;
	}
</script>

<div class="field-grid">
	{#each fields as field (field.name)}
		{#if !field.hidden}
			<div
				style="
					grid-column: {fieldSpan(field.width, 'col')};
					grid-row: {fieldSpan(field.rows, 'row')}"
			>
				{#if node.content[field.name] && shouldAddField(field.name)}
					<Control
						{field}
						node={node.uid}
						bind:data={node.content[field.name]}
						onchange={setDirty}
					/>
				{/if}
			</div>
		{/if}
	{/each}
</div>

<style>
	@layer panel {
		.field-grid {
			display: grid;
			grid-template-columns: repeat(100, minmax(0, 1fr));
		}
	}
</style>
