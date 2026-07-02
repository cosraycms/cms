<script lang="ts">
	import type { EntriesField, Field } from '$types/fields';

	import Element from '$shell/controls/Element.svelte';
	import Entries from '$shell/controls/Entries.svelte';
	import Group from '$shell/controls/Group.svelte';
	import Primitive from '$shell/controls/Primitive.svelte';
	import Repeater from '$shell/controls/Repeater.svelte';

	type Props = {
		field: Field;
		node?: string;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		data: any;
		onchange?: () => void;
	};

	let { field, node = '', data = $bindable(), onchange }: Props = $props();

	const PRIMITIVES = [
		'text',
		'textarea',
		'iframe',
		'number',
		'checkbox',
		'option',
		'date',
		'time',
		'datetime',
		'hidden',
	];

	let name = $derived(field.control?.name ?? '');
</script>

{#if PRIMITIVES.includes(name)}
	<Primitive {field} bind:data {onchange} />
{:else if name === 'element'}
	<Element {field} bind:data {node} {onchange} />
{:else if name === 'group'}
	<Group {field} bind:data {onchange} />
{:else if name === 'repeater'}
	<Repeater {field} bind:data {onchange} />
{:else if name === 'entries'}
	<Entries field={field as EntriesField} bind:data {node} />
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
