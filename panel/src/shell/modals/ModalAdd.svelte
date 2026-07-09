<script lang="ts">
	import { __ } from '$lib/locale';
	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import Button from '$shell/Button.svelte';

	type Props = {
		add: (index: number | null, before: boolean, type: string) => void;
		close: () => void;
		index: number | null;
		types: { id: string; label: string }[];
	};

	let { add, close, index, types }: Props = $props();

	let type: string | null = $state(null);
	let disabled = $derived(type === null);

	function addContent(before: boolean) {
		return () => {
			if (type !== null) {
				add(index, before, type);
				close();
			}
		};
	}

	function setType(t: string) {
		return () => (type = t);
	}
</script>

<ModalHeader>
	{__('field:add-content-type')}
</ModalHeader>
<ModalBody>
	<div class="cms-modal-add-types">
		{#if types.length > 0}
			{#each types as t}
				<Button
					class="cms-modal-add-type {t.id === type ? 'is-selected' : ''}"
					onclick={setType(t.id)}
				>
					<span>
						{t.label}
					</span>
				</Button>
			{/each}
		{/if}
	</div>
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={close}>
			{__('common:cancel')}
		</Button>
		<Button variant="primary" onclick={addContent(true)} {disabled}>
			{index === null ? __('common:insert') : __('field:insert-before')}
		</Button>
		{#if index !== null}
			<Button variant="primary" onclick={addContent(false)} {disabled}>
				{__('field:insert-after')}
			</Button>
		{/if}
	</div>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-add-types {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: var(--space-4);
			margin-bottom: var(--space-8);
		}

		:global(.cms-modal-add-type) {
			border: 1px solid var(--color-info);
			background-color: var(--color-white);
			color: var(--color-info);
		}

		:global(.cms-modal-add-type.is-selected) {
			background-color: var(--color-info);
			color: var(--color-white);
		}
	}
</style>
