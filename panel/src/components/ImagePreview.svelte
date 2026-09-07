<script lang="ts">
	import { __ } from '$lib/locale';
	import { ModalHeader, ModalBody } from '$components/modal';

	let { image }: { image: string } = $props();
</script>

<ModalHeader>{__('common:preview')}</ModalHeader>
<ModalBody>
	<div class="cms-image-preview">
		<img src={image} alt={__('common:preview')} />
	</div>
</ModalBody>

<style>
	@layer panel {
		.cms-image-preview {
			display: flex;
			align-items: center;
			justify-content: center;

			& img {
				max-width: 100%;
				max-height: calc(100dvh - 10rem);
				object-fit: contain;
			}
		}
	}
</style>
