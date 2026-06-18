<script lang="ts">
	import type { ModalFunctions } from '$shell/modal';

	import { getContext } from 'svelte';
	import { _ } from '$lib/locale';
	import NavToggle from '$shell/NavToggle.svelte';
	import Button from '$shell/Button.svelte';
	import ButtonMenu from '$shell/ButtonMenu.svelte';
	import ButtonMenuEntry from '$shell/ButtonMenuEntry.svelte';
	import IcoTrash from '$shell/icons/IcoTrash.svelte';
	import IcoSave from '$shell/icons/IcoSave.svelte';
	import IcoEye from '$shell/icons/IcoEye.svelte';
	import ModalRemove from '$shell/modals/ModalRemove.svelte';
	import { remove as removeNode } from '$lib/node';

	type Props = {
		uid: string;
		collectionPath: string;
		deletable: boolean;
		locked?: boolean;
		save: (publish: boolean) => void | Promise<unknown>;
		preview: (() => void) | null;
	};

	let {
		uid = $bindable(),
		collectionPath,
		deletable,
		locked = false,
		save,
		preview,
	}: Props = $props();

	let { open, close } = getContext<ModalFunctions>('modal');

	async function remove() {
		open(
			ModalRemove,
			{
				close,
				proceed: () => {
					removeNode(uid, collectionPath);
					close();
				},
			},
			{},
		);
	}
</script>

<div class="headerbar">
	<NavToggle />
	<div class="controls cms-node-controls">
		{#if deletable && !locked}
			<Button
				class="danger"
				icon={IcoTrash}
				onclick={remove}>
				{_('Löschen')}
			</Button>
		{/if}
		{#if preview}
			<Button
				class="secondary"
				icon={IcoEye}
				onclick={preview}>
				{_('Vorschau')}
			</Button>
		{/if}
		{#if !locked}
			<ButtonMenu
				class="primary"
				icon={IcoSave}
				onclick={() => save(false)}
				label={_('Speichern')}>
				{#snippet children(closeMenu)}
					<ButtonMenuEntry
						onclick={() => {
							save(true);
							closeMenu();
						}}>
						{_('Speichern und veröffentlichen')}
					</ButtonMenuEntry>
				{/snippet}
			</ButtonMenu>
		{/if}
	</div>
</div>

<style lang="postcss">
	.cms-node-controls {
		display: flex;
		flex-direction: row;
		justify-content: flex-end;
		gap: var(--cms-space-4);
		padding: var(--cms-space-6) var(--cms-space-4);
	}
</style>
