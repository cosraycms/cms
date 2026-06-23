<script lang="ts">
	import { ZXX, type TextData } from '$types/data';
	import type { SimpleField } from '$types/fields';

	import Field from '$shell/Field.svelte';
	import Label from '$shell/Label.svelte';

	type Props = {
		field: SimpleField;
		data: TextData;
	};

	let { field, data = $bindable() }: Props = $props();
</script>

<Field {field}>
	<Label of={field.name}>
		{field.label}
	</Label>
	<div class="cms-field-control">
		<input
			class="cms-input"
			id={field.name}
			name={field.name}
			type="text"
			required={field.required}
			bind:value={data.value[ZXX]}
		/>
	</div>
</Field>
