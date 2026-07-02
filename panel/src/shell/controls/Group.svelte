<script lang="ts">
	import type { GroupField } from '$types/controls';
	import type { SimpleField } from '$types/fields';

	import { ensureNeutral } from '$lib/content';
	import { ZXX } from '$types/data';
	import Field from '$shell/Field.svelte';
	import Label from '$shell/Label.svelte';
	import SubControl from './SubControl.svelte';

	type Props = {
		field: SimpleField;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		data: any;
	};

	let { field, data = $bindable() }: Props = $props();

	let fields = $derived((field.control.props.fields as GroupField[]) ?? []);

	$effect(() => {
		data.value = ensureNeutral(data.value, {});

		for (const sub of fields) {
			if (!(sub.key in data.value[ZXX])) {
				data.value[ZXX][sub.key] = null;
			}
		}
	});
</script>

<Field {field}>
	<Label of={field.name}>
		{field.label}
	</Label>
	<div class="cms-field-control cms-group">
		{#if data.value?.[ZXX]}
			{#each fields as sub (sub.key)}
				<div class="cms-group-field">
					<SubControl
						descriptor={sub.control}
						id={`${field.name}-${sub.key}`}
						label={sub.label ?? sub.key}
						bind:value={data.value[ZXX][sub.key]}
					/>
				</div>
			{/each}
		{/if}
	</div>
</Field>

<style>
	@layer panel {
		.cms-group {
			display: flex;
			flex-wrap: wrap;
			gap: 0.75rem;
		}

		.cms-group-field {
			flex: 1 1 12rem;
		}
	}
</style>
