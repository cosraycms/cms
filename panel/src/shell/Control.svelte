<script lang="ts">
	import type { Component } from 'svelte';
	import type { Field } from '$types/fields';

	import controls from '$lib/controls';

	type Props = {
		field: Field;
		node?: string;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		data: any;
	};

	let { field, node = '', data = $bindable() }: Props = $props();

	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	type AnyComponent = Component<any, any, any>;

	let name = $derived(field.control?.name);
	let Ctrl = $derived(controls[name as keyof typeof controls] as AnyComponent | undefined);
</script>

{#if Ctrl}
	<Ctrl {field} {node} bind:data />
{:else}
	<div class="cms-control-unknown">
		Unknown control "{name}" for field "{field.name}" ({field.type})
	</div>
{/if}

<style>
	@layer panel {
		.cms-control-unknown {
			padding: 0.75rem;
			border: 1px dashed var(--color-danger);
			border-radius: 0.375rem;
			color: var(--color-danger);
			font-size: 0.875rem;
		}
	}
</style>
