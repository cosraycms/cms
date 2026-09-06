<script lang="ts">
	import type { FileItem, LocaleMap, UploadType } from '$types/data';

	import { ZXX } from '$types/data';
	import { localeTitle, resolveFallback } from '$lib/fallback';
	import { __ } from '$lib/locale';
	import Upload from '$components/Upload.svelte';
	import FallbackMedia from '$components/media/FallbackMedia.svelte';

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
	let configuredLocales = $derived(locales?.all ?? []);
	let items = $state<FileItem[]>([]);

	function hasItems(candidate: FileItem[] | undefined): boolean {
		return candidate?.some((item) => typeof item.uid === 'string' && item.uid !== '') ?? false;
	}

	$effect(() => {
		items = [...(value[active] ?? [])];
	});

	let fallback = $derived(
		field.translateMode === 'asymmetric' && !hasItems(items)
			? resolveFallback(value, active, configuredLocales, hasItems)
			: null,
	);

	function commit() {
		value[active] = items;
		notify();
	}

	function sourceLabel(source: string): string {
		const language =
			source === ZXX ? __('field:shared-content') : localeTitle(configuredLocales, source);

		return __('field:fallback-from', { language });
	}
</script>

{#key active}
	{#if fallback}
		<FallbackMedia items={fallback.value} {type} label={sourceLabel(fallback.locale)} />
	{/if}
	<Upload
		{type}
		limit={field.limit}
		required={field.required ?? false}
		name={field.name}
		translate={field.translateMode === 'asymmetric' ? false : (field.translate ?? false)}
		contentLocale={locale}
		identity={active}
		{locales}
		bind:items
		notify={commit}
	/>
{/key}
