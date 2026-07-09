<script lang="ts">
	import type { LibraryItem } from '$shell/LibraryBrowser.svelte';

	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import { __ } from '$lib/locale';
	import Button from '$shell/Button.svelte';
	import LibraryBrowser from '$shell/LibraryBrowser.svelte';

	type Props = {
		kind?: 'image' | 'video' | 'file' | null;
		pick: (item: LibraryItem) => void;
		close: () => void;
	};

	let { kind = null, pick, close }: Props = $props();
</script>

<ModalHeader>{__('media:choose-from-library')}</ModalHeader>
<ModalBody>
	<LibraryBrowser {kind} {pick} />
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={close}>
			{__('common:cancel')}
		</Button>
	</div>
</ModalFooter>
