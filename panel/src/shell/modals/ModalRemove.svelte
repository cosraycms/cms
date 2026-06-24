<script lang="ts">
	import { _ } from '$lib/locale';
	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import Button from '$shell/Button.svelte';

	type Props = {
		close: any;
		proceed: any;
		message?: any;
	};

	let { close, proceed, message = null }: Props = $props();
</script>

<ModalHeader>{_('Dokument löschen')}</ModalHeader>
<ModalBody>
	<p>
		{_('Soll der Eintrag wirklich gelöscht werden?')}
	</p>
	{#if message}
		<p class="cms-modal-remove-message">{message}</p>
	{/if}
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={proceed}>
			{_('Ja, Eintrag löschen')}
		</Button>
		<Button variant="secondary" onclick={close}>
			{_('Nein, Eintrag nicht löschen')}
		</Button>
	</div>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-remove-message {
			margin-top: var(--space-6);
			font-weight: 600;
		}
	}
</style>
