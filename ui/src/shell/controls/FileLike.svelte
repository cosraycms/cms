<script lang="ts">
	import { system, systemLocale } from '$lib/sys';
	import Field from '$shell/Field.svelte';
	import Upload from '$shell/Upload.svelte';
	import LabelDiv from '$shell/LabelDiv.svelte';
	import type { FileData } from '$types/data';
	import type { ImageField } from '$types/fields';

	type Props = {
		field: ImageField;
		data: FileData;
		node: string;
		type: 'image' | 'file';
	};

	let { field, data = $bindable(), node, type }: Props = $props();

	let lang = $state(systemLocale($system));
</script>

<Field
	{field}
	class="cms-field-column">
	<LabelDiv
		translate={field.translate}
		bind:lang>
		{field.label}
	</LabelDiv>
	<div class="cms-field-content">
		{#if field.translateMode === 'asymmetric' && !Array.isArray(data.files)}
			{#each $system.locales as locale (locale.id)}
				{#if locale.id === lang}
					<Upload
						{type}
						limit={field.limit}
						path="{$system.prefix}/media/{type}/node/{node}"
						required={field.required}
						name={field.name}
						translate={false}
						bind:assets={data.files[locale.id]} />
				{/if}
			{/each}
		{:else if Array.isArray(data.files)}
			<Upload
				{type}
				limit={field.limit}
				path="{$system.prefix}/media/{type}/node/{node}"
				required={field.required}
				name={field.name}
				translate={field.translate}
				bind:assets={data.files} />
		{/if}
	</div>
</Field>
