<script lang="ts">
	import { __ } from '$lib/locale';
	import Button from '$components/Button.svelte';
	import { ModalHeader, ModalBody, ModalFooter } from '$components/modal';

	let {
		title,
		body,
		type = 'info',
		close,
	}: {
		title?: string;
		body: string;
		type?: string;
		close: () => void;
	} = $props();
</script>

<ModalHeader>{title ?? __('common:dialog')}</ModalHeader>
<ModalBody>
	<p class="cms-dialog-message" class:is-error={type === 'error'}>{body}</p>
</ModalBody>
<ModalFooter>
	<Button variant="secondary" data-dialog-focus onclick={close}>{__('common:ok')}</Button>
</ModalFooter>

<style>
	@layer panel {
		.cms-dialog-message {
			color: var(--cms-color-text-muted);
			font-size: var(--cms-font-size-sm);
			line-height: 1.5;

			&.is-error {
				color: var(--cms-color-danger-text);
			}
		}
	}
</style>
