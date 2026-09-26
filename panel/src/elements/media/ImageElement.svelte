<svelte:options customElement={{ tag: 'cosray-image', shadow: 'none' }} />

<script lang="ts">
	import type { AssetMap, FileItem, LocaleMap, Meta } from '$types/data';

	import { untrack } from 'svelte';
	import { ZXX } from '$lib/content';
	import { provideAssets } from '$lib/assets';
	import MediaControl from './MediaControl.svelte';

	type Props = {
		value?: LocaleMap<FileItem[]>;
		// The gallery settings, kept as the field's meta.
		meta?: Meta;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		field?: any;
		node?: string;
		locale?: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		assets?: AssetMap;
		settings?: HTMLElement;
	};

	let {
		value = {},
		meta,
		field = { name: 'image' },
		node = '',
		locale = ZXX,
		locales,
		assets = {},
		settings,
	}: Props = $props();

	// The SSR payload seeds the store once; later host re-assignments
	// merge in through the effect below.
	const assetStore = provideAssets(() => assets);

	$effect(() => {
		assetStore.update((map) => ({ ...assets, ...map }));
	});

	function sync(): LocaleMap<FileItem[]> {
		return value ?? {};
	}

	// Synchronous init: children mount before effects run and would
	// otherwise start from an empty map; the effect handles later host
	// re-assignments.
	let map: LocaleMap<FileItem[]> = $state(sync());
	let settingsMeta: Meta | undefined = $state(untrack(() => meta));

	$effect(() => {
		map = sync();
	});

	$effect(() => {
		settingsMeta = meta;
	});

	// Meta travels only once the element has any, so a plain image field
	// keeps submitting without a meta member.
	function notify() {
		const detail: { value: LocaleMap<FileItem[]>; meta?: Meta } = { value: map };

		if (settingsMeta !== undefined) {
			detail.meta = $state.snapshot(settingsMeta);
		}

		$host().dispatchEvent(
			new CustomEvent('cosray-change', { detail, bubbles: true, composed: true }),
		);
	}

	function updateMeta(next: Meta) {
		settingsMeta = next;
		notify();
	}
</script>

<MediaControl
	type="image"
	bind:value={map}
	{field}
	{node}
	{locale}
	{locales}
	{settings}
	meta={settingsMeta}
	{updateMeta}
	{notify}
/>
