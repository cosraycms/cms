<script lang="ts">
	import { _ } from '$lib/locale';

	let { image } = $props();
</script>

<div class="image-preview">
	<img src="{image}?resize=width&w=1280" alt={_('Vorschau')} style="max-height: 90vh" />
</div>

<style>
	@layer panel {
		.image-preview {
			display: flex;
			align-items: center;
			justify-content: center;
		}

		img {
			max-width: 100%;
			min-width: 40rem;
		}
	}
</style>
