<script lang="ts">
	import type { EntryData } from '$types/data';
	import type { ModalFunctions } from '$shell/modal';
	import type { Component } from 'svelte';

	import { getContext } from 'svelte';
	import IcoTrash from '$shell/icons/IcoTrash.svelte';
	import IcoArrowUp from '$shell/icons/IcoArrowUp.svelte';
	import IcoArrowDown from '$shell/icons/IcoArrowDown.svelte';
	import IcoCollapse from '$shell/icons/IcoCollapse.svelte';
	import IcoExpand from '$shell/icons/IcoExpand.svelte';
	import ModalRemove from '$shell/modals/ModalRemove.svelte';
	import { setDirty } from '$lib/state';

	type Props = {
		data: EntryData[];
		entry: EntryData;
		index: number;
		collapsed: boolean;
		toggleCollapse: () => void;
	};

	let { data = $bindable(), entry, index, collapsed, toggleCollapse }: Props = $props();

	let { open, close } = getContext<ModalFunctions>('modal');
	let first = $derived(data?.indexOf(entry) === 0);
	let last = $derived(data?.indexOf(entry) === data.length - 1);

	async function remove() {
		open(
			ModalRemove as Component,
			{
				close,
				proceed: () => {
					data.splice(index, 1);
					data = data;
					setDirty();
					close();
				},
			},
			{},
		);
	}

	function up() {
		if (first) {
			return;
		}

		data.splice(index - 1, 0, data.splice(index, 1)[0]);
		data = data;
		setDirty();
	}

	function down() {
		if (last) {
			return;
		}

		data.splice(index + 1, 0, data.splice(index, 1)[0]);
		data = data;
		setDirty();
	}
</script>

<div class="cms-entry-controls">
	<button
		type="button"
		class="collapse-btn"
		title={collapsed ? 'Expand' : 'Collapse'}
		onclick={toggleCollapse}
	>
		{#if collapsed}
			<IcoExpand />
		{:else}
			<IcoCollapse />
		{/if}
	</button>
	<button type="button" class="up-down" disabled={first} title="Move up" onclick={up}>
		<IcoArrowUp />
	</button>
	<button type="button" class="up-down" disabled={last} title="Move down" onclick={down}>
		<IcoArrowDown />
	</button>
	<button type="button" class="remove" title="Remove entry" onclick={remove}>
		<IcoTrash />
	</button>
</div>

<style>
	@layer panel {
		.cms-entry-controls {
			display: flex;
			flex-direction: row;
			align-items: center;
			gap: var(--space-2);
		}

		div button {
			height: var(--space-4);
			width: var(--space-4);

			&[disabled] {
				color: rgb(209 213 219);
			}
		}

		.remove {
			color: var(--color-warning);
		}

		.collapse-btn {
			color: var(--color-neutral-500);
		}
	}
</style>
