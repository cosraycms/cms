<script lang="ts">
	import type { FileItem } from '$types/data';
	import { useAssets } from '$lib/assets';

	let { file }: { file: FileItem } = $props();

	const assets = useAssets();

	let info = $derived($assets[file.uid ?? '']);
</script>

<div class="cms-video">
	<!-- svelte-ignore a11y_media_has_caption -->
	<video controls preload="metadata" class="cms-video-player" src={info?.url ?? ''}></video>
</div>

<style>
	@layer panel {
		.cms-video {
			background: var(--cms-color-neutral-900);
		}

		.cms-video-player {
			display: block;
			width: 100%;
			max-height: 24rem;
		}
	}
</style>
