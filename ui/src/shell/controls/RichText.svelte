<script lang="ts">
	import { ensureLocales, ensureNeutral } from '$lib/content';
	import { system, systemLocale } from '$lib/sys';
	import Field from '$shell/Field.svelte';
	import RichTextEditor from '$shell/richtext/RichTextEditor.svelte';
	import LabelDiv from '$shell/LabelDiv.svelte';
	import { ZXX, type TextData } from '$types/data';
	import type { SimpleField } from '$types/fields';

	type Props = {
		field: SimpleField;
		data: TextData;
	};

	let { field, data = $bindable() }: Props = $props();

	let lang = $state(systemLocale($system));
	$effect(() => {
		data.value = field.translate
			? ensureLocales(data.value, '')
			: ensureNeutral(data.value, '');
	});
</script>

<Field {field}>
	<LabelDiv
		translate={field.translate}
		bind:lang>
		{field.label}
	</LabelDiv>
	{#if field.description}
		<div class="cms-field-description">
			{field.description}
		</div>
	{/if}
	<div class="cms-field-control">
		{#if field.translate}
			{#each $system.locales as locale (locale)}
				{#if locale.id === lang}
					<RichTextEditor
						name={field.name}
						required={field.required}
						bind:value={data.value[locale.id]} />
				{/if}
			{/each}
		{:else}
			<RichTextEditor
				name={field.name}
				required={field.required}
				bind:value={data.value[ZXX]} />
		{/if}
	</div>
</Field>
