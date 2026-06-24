<script lang="ts">
	import { setDirty } from '$lib/state';
	import { ZXX } from '$types/data';
	import Field from '$shell/Field.svelte';
	import type { BooleanData } from '$types/data';
	import type { SimpleField } from '$types/fields';

	type Props = {
		field: SimpleField;
		data: BooleanData;
	};

	let { field, data = $bindable() }: Props = $props();

	function onchange() {
		setDirty();
	}
</script>

<Field {field}>
	<div class="cms-field-control cms-checkbox-wrap">
		<div class="cms-checkbox-input-wrap">
			<input
				id={field.name}
				name={field.name}
				type="checkbox"
				class="cms-checkbox"
				disabled={field.immutable}
				bind:checked={data.value[ZXX]}
				{onchange}
			/>
		</div>
		<div class="cms-checkbox-content">
			<label for={field.name} class="cms-checkbox-label">
				{field.label}
			</label>
			{#if field.description}
				<p class="cms-field-help">{field.description}</p>
			{/if}
		</div>
	</div>
</Field>

<style>
	@layer panel {
		.cms-checkbox-wrap {
			position: relative;
			display: flex;
			align-items: flex-start;
		}

		.cms-checkbox-input-wrap {
			display: flex;
			height: var(--space-6);
			align-items: center;
		}

		.cms-checkbox-content {
			margin-left: var(--space-3);
			font-size: var(--font-size-sm);
			line-height: 1.5rem;
		}

		input[type='checkbox'] {
			border-width: 1.5px;
		}
	}
</style>
