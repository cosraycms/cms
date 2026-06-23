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

	function onchange() {
		setDirty();
	}
</script>

<Field {field}>
	<Label of={field.name}>
		{field.label}
	</Label>
	<div class="cms-field-control">
		<select
			class="cms-select"
			id={field.name}
			name={field.name}
			required={field.required}
			bind:value={data.value[ZXX]}
			{onchange}
		>
			{#each field.options as option}
				{#if typeof option === 'object'}
					<option value={option.value}>{option.label}</option>
				{:else}
					<option value={option}>{option}</option>
				{/if}
			{/each}
		</select>
	</div>
</Field>
