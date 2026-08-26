<script lang="ts">
	import type { Field } from '$types/fields';

	import { __ } from '$lib/locale';
	import Element from '$components/controls/Element.svelte';
	import Group from '$components/controls/Group.svelte';
	import Primitive from '$components/controls/Primitive.svelte';
	import Repeater from '$components/controls/Repeater.svelte';

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
{:else}
	<div class="cms-control-unknown">
		{__('field:unknown-control', { control: name, field: field.name, type: field.type })}
	</div>
{/if}

<style>
	@layer panel {
		.cms-control-unknown {
			padding: 0.75rem;
			border: 1px dashed var(--cms-color-danger);
			border-radius: 0.375rem;
			color: var(--cms-color-danger);
			font-size: 0.875rem;
		}
	}
</style>
