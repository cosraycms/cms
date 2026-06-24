<script lang="ts">
	import type { Block } from '$types/data';
	import type { ModalFunctions } from '$shell/modal';

	import { getContext } from 'svelte';
	import type { Component } from 'svelte';
	import IcoTrash from '$shell/icons/IcoTrash.svelte';
	import IcoArrowUp from '$shell/icons/IcoArrowUp.svelte';
	import IcoArrowDown from '$shell/icons/IcoArrowDown.svelte';
	import IcoCirclePlus from '$shell/icons/IcoCirclePlus.svelte';
	import ModalRemove from '$shell/modals/ModalRemove.svelte';
	import { setDirty } from '$lib/state';

	type Props = {
		data: Block[];
		item: Block;
		index: number;
		add: () => void;
		dropdown?: boolean;
	};

	let {
		data = $bindable(),
		item = $bindable(),
		index = $bindable(),
		add,
		dropdown = false,
	}: Props = $props();
	let { open, close } = getContext<ModalFunctions>('modal');
	let first = $derived(data?.indexOf(item) === 0);
	let last = $derived(data?.indexOf(item) === data.length - 1);

	async function remove() {
		open(
			ModalRemove as Component<any>,
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

<div
	class="cms-block-cell-buttons"
	class:cms-block-cell-buttons-inline={!dropdown}
	class:cms-block-cell-buttons-dropdown={dropdown}
>
	<button class="remove" onclick={remove}>
		<IcoTrash />
	</button>
	<button class="up-down" disabled={last} onclick={down}>
		<IcoArrowDown />
	</button>
	<button class="up-down" disabled={first} onclick={up}>
		<IcoArrowUp />
	</button>
	<button class="add" onclick={add}>
		<IcoCirclePlus />
	</button>
</div>

<style>
	@layer panel {
		.cms-block-cell-buttons {
			display: flex;
			flex: 1 1 auto;
			flex-direction: row;
			align-items: center;
			gap: var(--cms-space-3);
			padding: var(--cms-space-2) 0;
		}

		.cms-block-cell-buttons-inline {
			justify-content: flex-end;
			margin-right: var(--cms-space-3);
		}

		.cms-block-cell-buttons-dropdown {
			justify-content: center;
		}

		div button {
			height: var(--cms-space-4);
			width: var(--cms-space-4);

			&[disabled] {
				color: rgb(209 213 219);
			}
		}

		.remove {
			color: var(--cms-color-warning-700);
		}

		.add {
			color: var(--cms-color-info-700);
		}
	}
</style>
