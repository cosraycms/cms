<script lang="ts">
	import type { ControlDescriptor } from '$types/controls';
	import type { SimpleField } from '$types/fields';

	import { _ } from '$lib/locale';
	import { ensureNeutral } from '$lib/content';
	import { setDirty } from '$lib/state';
	import { ZXX } from '$types/data';
	import Button from '$shell/Button.svelte';
	import Field from '$shell/Field.svelte';
	import Label from '$shell/Label.svelte';
	import SubControl from './SubControl.svelte';

	type Props = {
		field: SimpleField;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		data: any;
	};

	let { field, data = $bindable() }: Props = $props();

	let opts = $derived(
		field.control.props as {
			item: ControlDescriptor;
			min?: number;
			max?: number;
		},
	);
	let items = $derived((data.value?.[ZXX] as unknown[]) ?? []);

	$effect(() => {
		data.value = ensureNeutral(data.value, []);
	});

	function add() {
		data.value[ZXX] = [...items, null];
		setDirty();
	}

	function remove(index: number) {
		data.value[ZXX] = items.filter((_item, i) => i !== index);
		setDirty();
	}
</script>

<Field {field}>
	<Label of={field.name}>
		{field.label}
	</Label>
	<div class="cms-field-control cms-repeater">
		{#each items as _item, index (index)}
			<div class="cms-repeater-item">
				<SubControl
					descriptor={opts.item}
					id={`${field.name}-${index}`}
					label={`${index + 1}.`}
					bind:value={data.value[ZXX][index]}
				/>
				<Button onclick={() => remove(index)}>{_('Entfernen')}</Button>
			</div>
		{/each}
		{#if opts.max === undefined || items.length < opts.max}
			<div>
				<Button onclick={add}>{_('Hinzufügen')}</Button>
			</div>
		{/if}
	</div>
</Field>

<style>
	@layer panel {
		.cms-repeater {
			display: flex;
			flex-direction: column;
			gap: 0.5rem;
		}

		.cms-repeater-item {
			display: flex;
			align-items: end;
			gap: 0.5rem;

			& :global(> :first-child) {
				flex-grow: 1;
			}
		}
	}
</style>
