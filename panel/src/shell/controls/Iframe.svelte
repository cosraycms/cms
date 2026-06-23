<script lang="ts">
	import { ZXX, type TextData } from '$types/data';
	import type { SimpleField } from '$types/fields';

	import { ensureLocales, ensureNeutral } from '$lib/content';
	import { setDirty } from '$lib/state';
	import { system, systemLocale } from '$lib/sys';
	import Field from '$shell/Field.svelte';
	import Label from '$shell/Label.svelte';

	type Props = {
		field: SimpleField;
		data: TextData;
	};

	let { field, data = $bindable() }: Props = $props();
	let lang = $state(systemLocale($system));
	$effect(() => {
		data.value = field.translate ? ensureLocales(data.value, '') : ensureNeutral(data.value, '');
	});

	function oninput() {
		setDirty();
	}
</script>

<Field {field}>
	<Label of={field.name} translate={field.translate} bind:lang>
		{field.label}
	</Label>
	<div class="cms-field-control">
		{#if field.translate}
			{#each $system.locales as locale}
				{#if locale.id === lang}
					<textarea
						class="cms-textarea iframe"
						id={field.name}
						name={field.name}
						required={field.required}
						bind:value={data.value[locale.id]}
						{oninput}
					>
					</textarea>
				{/if}
			{/each}
		{:else}
			<textarea
				class="cms-textarea iframe"
				id={field.name}
				name={field.name}
				required={field.required}
				bind:value={data.value[ZXX]}
				{oninput}
			>
			</textarea>
		{/if}
	</div>
</Field>
