<script lang="ts">
	import { __ } from '$lib/locale';

	let { image } = $props();
</script>

<div class="image-preview">
	<img src={image} alt={__('common:preview')} style="max-height: 90vh" />
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
