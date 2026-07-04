<svelte:options customElement={{ tag: 'cosray-video', shadow: 'none' }} />

<script lang="ts">
	import type { AssetMap, FileItem, LocaleMap } from '$types/data';

	import { ZXX } from '$types/data';
	import { provideAssets } from '$lib/assets';
	import MediaControl from './MediaControl.svelte';

	type Props = {
		value?: LocaleMap<FileItem[]>;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		field?: any;
		node?: string;
		locale?: string;
		assets?: AssetMap;
	};

	let {
		value = {},
		field = { name: 'video' },
		node = '',
		locale = ZXX,
		assets = {},
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

	$effect(() => {
		map = sync();
	});

	function notify() {
		$host().dispatchEvent(
			new CustomEvent('cosray-change', {
				detail: { value: map },
				bubbles: true,
				composed: true,
			}),
		);
	}
</script>

<MediaControl type="video" bind:value={map} {field} {node} {locale} {notify} />
