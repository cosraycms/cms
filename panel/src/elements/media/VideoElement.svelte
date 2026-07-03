<svelte:options customElement={{ tag: 'cosray-video', shadow: 'none' }} />

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

	let { value = {}, field = { name: 'video' }, node = '', locale = ZXX }: Props = $props();

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
