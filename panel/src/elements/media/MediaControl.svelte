<script lang="ts">
	import type { FileItem, LocaleMap, Meta, UploadType } from '$types/data';

	import { ZXX } from '$types/data';
	import { localeTitle, resolveFallback } from '$lib/fallback';
	import { __ } from '$lib/locale';
	import MediaField from '$components/MediaField.svelte';
	import FallbackMedia from '$components/media/FallbackMedia.svelte';

	type FieldInfo = {
		name: string;
		immutable?: boolean;
		translate?: boolean;
		translateMode?: 'symmetric' | 'asymmetric';
		limit?: { min: number; max: number };
		presentation?: string;
	};

	type Props = {
		type: UploadType;
		value: LocaleMap<FileItem[]>;
		field: FieldInfo;
		node: string;
		locale: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		settings?: HTMLElement;
		meta?: Meta;
		updateMeta?: (meta: Meta) => void;
		notify: () => void;
	};

	let {
		type,
		value = $bindable(),
		field,
		node,
		locale,
		locales,
		settings,
		meta,
		updateMeta,
		notify,
	}: Props = $props();

	let active = $derived(field.translateMode === 'asymmetric' ? locale : ZXX);
	let configuredLocales = $derived(locales?.all ?? []);

	function hasItems(candidate: FileItem[] | undefined): boolean {
		return candidate?.some((item) => typeof item.uid === 'string' && item.uid !== '') ?? false;
	}

	let fallback = $derived(
		field.translateMode === 'asymmetric' && !hasItems(value[active])
			? resolveFallback(value, active, configuredLocales, hasItems)
			: null,
	);

	function add(identity: string, item: FileItem): void {
		value[identity] = field.limit?.max === 1 ? [item] : [...(value[identity] ?? []), item];
	}

	function sourceLabel(source: string): string {
		const language =
			source === ZXX ? __('field:shared-content') : localeTitle(configuredLocales, source);

		return __('field:fallback-from', { language });
	}
</script>

<!-- A keyed item keeps pending uploads bound to their original locale after switching. -->
{#each [active] as identity (identity)}
	{#if fallback}
		<FallbackMedia items={fallback.value} {type} label={sourceLabel(fallback.locale)} />
	{/if}
	<MediaField
		{type}
		limit={field.limit}
		readonly={field.immutable ?? false}
		name={field.name}
		translate={field.translateMode === 'asymmetric' ? false : (field.translate ?? false)}
		contentLocale={locale}
		{identity}
		{locales}
		bind:items={() => value[identity] ?? [], (items) => (value[identity] = items)}
		add={(item) => add(identity, item)}
		presentation={field.presentation}
		{settings}
		{meta}
		{updateMeta}
		{notify}
	/>
{/each}
