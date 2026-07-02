<svelte:options customElement={{ tag: 'cosray-image', shadow: 'none' }} />

<script lang="ts">
	import type { FileItem, LocaleMap } from '$types/data';

	import { ZXX } from '$types/data';
	import MediaControl from './MediaControl.svelte';

	type Props = {
		value?: LocaleMap<FileItem[]>;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		field?: any;
		node?: string;
		locale?: string;
	};

	let { value = {}, field = { name: 'image' }, node = '', locale = ZXX }: Props = $props();

	let map: LocaleMap<FileItem[]> = $state({});

	$effect(() => {
		map = value ?? {};
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

<MediaControl type="image" bind:value={map} {field} {node} {locale} {notify} />
