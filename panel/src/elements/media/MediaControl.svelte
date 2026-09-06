<script lang="ts">
	import type { FileItem, LocaleMap, UploadType } from '$types/data';

	import { ZXX } from '$types/data';
	import Upload from '$components/Upload.svelte';

	type FieldInfo = {
		name: string;
		required?: boolean;
		translate?: boolean;
		translateMode?: 'symmetric' | 'asymmetric';
		limit?: { min: number; max: number };
	};

	type Props = {
		type: UploadType;
		value: LocaleMap<FileItem[]>;
		field: FieldInfo;
		node: string;
		locale: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		notify: () => void;
	};

	let { type, value = $bindable(), field, node, locale, locales, notify }: Props = $props();

	let active = $derived(field.translateMode === 'asymmetric' ? locale : ZXX);

	$effect(() => {
		value[active] ??= [];
	});
</script>

{#if value[active]}
	{#key active}
		<Upload
			{type}
			limit={field.limit}
			required={field.required ?? false}
			name={field.name}
			translate={field.translateMode === 'asymmetric' ? false : (field.translate ?? false)}
			locale={field.translateMode === 'asymmetric' ? ZXX : locale}
			contentLocale={locale}
			identity={active}
			{locales}
			bind:items={value[active]}
			{notify}
		/>
	{/key}
{/if}
