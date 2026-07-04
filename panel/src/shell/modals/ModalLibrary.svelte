<script lang="ts">
	import type { LibraryItem } from '$shell/LibraryBrowser.svelte';

	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import { _ } from '$lib/locale';
	import Button from '$shell/Button.svelte';
	import LibraryBrowser from '$shell/LibraryBrowser.svelte';

	type Props = {
		kind?: 'image' | 'video' | 'file' | null;
		pick: (item: LibraryItem) => void;
		close: () => void;
	};

	let { kind = null, pick, close }: Props = $props();
</script>

<ModalHeader>{_('Aus Bibliothek wählen')}</ModalHeader>
<ModalBody>
	<LibraryBrowser {kind} {pick} />
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={close}>
			{_('Abbrechen')}
		</Button>
	</div>
</ModalFooter>
