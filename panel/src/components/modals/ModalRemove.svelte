<script lang="ts">
	import { __ } from '$lib/locale';
	import { ModalHeader, ModalBody, ModalFooter } from '$components/modal';
	import Button from '$components/Button.svelte';

	type Props = {
		close: any;
		proceed: any;
		message?: any;
		title?: string;
		question?: string;
		confirm?: string;
		cancel?: string;
	};

	let {
		close,
		proceed,
		message = null,
		title = __('document:delete'),
		question = __('field:confirm-delete'),
		confirm = __('field:confirm-delete-entry'),
		cancel = __('field:cancel-delete-entry'),
	}: Props = $props();
</script>

<ModalHeader>{title}</ModalHeader>
<ModalBody>
	<p>
		{question}
	</p>
	{#if message}
		<p class="cms-modal-remove-message">{message}</p>
	{/if}
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={proceed}>
			{confirm}
		</Button>
		<Button variant="secondary" onclick={close}>
			{cancel}
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
