<script lang="ts">
	import type { Block } from '$types/data';

	import { mount, unmount } from 'svelte';
	import { cosray } from '$lib/bridge';
	import IcoTrash from '$shell/icons/IcoTrash.svelte';
	import IcoArrowUp from '$shell/icons/IcoArrowUp.svelte';
	import IcoArrowDown from '$shell/icons/IcoArrowDown.svelte';
	import IcoCirclePlus from '$shell/icons/IcoCirclePlus.svelte';
	import ModalRemove from '$shell/modals/ModalRemove.svelte';
	import { useNotify } from './notify';

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
	const notify = useNotify();
	let first = $derived(data?.indexOf(item) === 0);
	let last = $derived(data?.indexOf(item) === data.length - 1);

	function remove() {
		const handle = cosray().modal.open((host) => {
			const app = mount(ModalRemove, {
				target: host,
				props: {
					close: () => handle.close(),
					proceed: () => {
						data.splice(index, 1);
						data = data;
						notify();
						handle.close();
					},
				},
			});

			return () => void unmount(app);
		});
	}

	function up() {
		if (first) {
			return;
		}

		data.splice(index - 1, 0, data.splice(index, 1)[0]);
		data = data;
		notify();
	}

	function down() {
		if (last) {
			return;
		}

		data.splice(index + 1, 0, data.splice(index, 1)[0]);
		data = data;
		notify();
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
			gap: var(--space-3);
			padding: var(--space-2) 0;
		}

		.cms-block-cell-buttons-inline {
			justify-content: flex-end;
			margin-right: var(--space-3);
		}

		.cms-block-cell-buttons-dropdown {
			justify-content: center;
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

		.add {
			color: var(--color-info);
		}
	}
</style>
