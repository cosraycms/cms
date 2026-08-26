<script lang="ts">
	import { __ } from '$lib/locale';
	import { ModalHeader, ModalBody, ModalFooter } from '$components/modal';
	import Button from '$components/Button.svelte';

	type Props = {
		close: any;
		proceed: any;
		message?: any;
	};

	let { close, proceed, message = null }: Props = $props();
</script>

<ModalHeader>{__('document:delete')}</ModalHeader>
<ModalBody>
	<p>
		{__('field:confirm-delete')}
	</p>
	{#if message}
		<p class="cms-modal-remove-message">{message}</p>
	{/if}
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={proceed}>
			{__('field:confirm-delete-entry')}
		</Button>
		<Button variant="secondary" onclick={close}>
			{__('field:cancel-delete-entry')}
		</Button>
	</div>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-remove-message {
			margin-top: var(--cms-space-6);
			font-weight: 500;
		}
	}
</style>
