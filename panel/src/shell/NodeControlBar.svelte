<script lang="ts">
	import type { ModalFunctions } from '$shell/modal';

	import { getContext } from 'svelte';
	import { _ } from '$lib/locale';
	import Breadcrumbs from '$shell/Breadcrumbs.svelte';
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
		collectionName: string;
		swapTarget?: string | null;
		deletable: boolean;
		locked?: boolean;
		save: (publish: boolean) => void | Promise<unknown>;
		preview: (() => void) | null;
	};

	let {
		uid = $bindable(),
		collectionPath,
		collectionName,
		swapTarget = '#main',
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

<header class="cms-node-topbar">
	<div class="inner">
		<div class="trail">
			<Breadcrumbs href={collectionPath} name={collectionName} {swapTarget} />
		</div>
		<div class="actions">
			{#if deletable && !locked}
				<Button variant="danger" icon={IcoTrash} onclick={remove}>
					{_('Löschen')}
				</Button>
			{/if}
			{#if preview}
				<Button variant="secondary" icon={IcoEye} onclick={preview}>
					{_('Vorschau')}
				</Button>
			{/if}
			{#if !locked}
				<ButtonMenu
					variant="primary"
					icon={IcoSave}
					onclick={() => save(false)}
					label={_('Speichern')}
				>
					{#snippet children(closeMenu)}
						<ButtonMenuEntry
							onclick={() => {
								save(true);
								closeMenu();
							}}
						>
							{_('Speichern und veröffentlichen')}
						</ButtonMenuEntry>
					{/snippet}
				</ButtonMenu>
			{/if}
		</div>
	</div>
</header>

<style>
	@layer panel {
		.cms-node-topbar {
			flex: 0 0 auto;
			border-bottom: 1px solid var(--color-border);
			background: var(--topbar-bg);
		}

		.inner {
			display: flex;
			width: 100%;
			max-width: var(--node-max-width);
			min-height: 4.25rem;
			align-items: center;
			gap: var(--space-4);
			margin: 0 auto;
			padding: 0 var(--space-6);
		}

		.trail {
			min-width: 0;
			flex: 1 1 auto;
		}

		.actions {
			display: flex;
			flex: 0 0 auto;
			align-items: center;
			justify-content: flex-end;
			gap: var(--space-4);
		}

		@media (max-width: 52rem) {
			.inner {
				min-height: 0;
				align-items: flex-start;
				flex-direction: column;
				padding: var(--space-3) var(--space-4);
			}

			.actions {
				width: 100%;
				justify-content: flex-start;
				gap: var(--space-2);
				flex-wrap: wrap;
			}
		}
	}
</style>
