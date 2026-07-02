<script lang="ts">
	import type { FileItem, LocaleMap, UploadType } from '$types/data';

	import { ZXX } from '$types/data';
	import Upload from '$shell/Upload.svelte';

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
		notify: () => void;
	};

	let { type, value = $bindable(), field, node, locale, notify }: Props = $props();

	let active = $derived(field.translateMode === 'asymmetric' ? locale : ZXX);

	$effect(() => {
		value[active] ??= [];
	});
</script>

{#if value[active]}
	<Upload
		{type}
		limit={field.limit}
		{node}
		required={field.required ?? false}
		name={field.name}
		translate={field.translateMode === 'asymmetric' ? false : (field.translate ?? false)}
		bind:assets={value[active]}
		{notify}
	/>
{/if}
