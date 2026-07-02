<script lang="ts">
	import { ZXX, type TextData } from '$types/data';
	import type { SimpleField } from '$types/fields';

	import { setDirty } from '$lib/state';
	import Field from '$shell/Field.svelte';
	import Label from '$shell/Label.svelte';

	type Props = {
		field: SimpleField;
		data: TextData;
	};

	let { field, data = $bindable() }: Props = $props();

	let display = $derived((field.control?.props?.display as string) ?? 'select');
	let options = $derived(
		(field.options ?? []).map((option) =>
			typeof option === 'object' ? option : { value: option, label: option },
		),
	);

	function onchange() {
		setDirty();
	}
</script>

<Field {field}>
	<Label of={field.name}>
		{field.label}
	</Label>
	<div class="cms-field-control">
		{#if display === 'radio'}
			<div class="cms-radio-group">
				{#each options as option}
					<label class="cms-radio">
						<input
							type="radio"
							name={field.name}
							value={option.value}
							required={field.required}
							disabled={field.immutable}
							bind:group={data.value[ZXX]}
							{onchange}
						/>
						{option.label}
					</label>
				{/each}
			</div>
		{:else}
			<select
				class="cms-select"
				id={field.name}
				name={field.name}
				required={field.required}
				bind:value={data.value[ZXX]}
				{onchange}
			>
				{#each options as option}
					<option value={option.value}>{option.label}</option>
				{/each}
			</select>
		{/if}
	</div>
</Field>

<style>
	@layer panel {
		.cms-radio-group {
			display: flex;
			flex-direction: column;
			gap: 0.25rem;
		}

		.cms-radio {
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}
	}
</style>
