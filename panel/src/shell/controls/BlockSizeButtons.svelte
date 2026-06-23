<script lang="ts">
	import type { Block } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { setDirty } from '$lib/state';
	import BlockButtonLabel from '$shell/controls/BlockButtonLabel.svelte';
	import IcoExpand from '$shell/icons/IcoExpand.svelte';
	import IcoCollapse from '$shell/icons/IcoCollapse.svelte';
	import IcoIndent from '$shell/icons/IcoIndent.svelte';
	import IcoUnindent from '$shell/icons/IcoUnindent.svelte';

	type Props = {
		item: Block;
		field: BlocksField;
		dropdown?: boolean;
	};

	let { item = $bindable(), field = $bindable(), dropdown = false }: Props = $props();
	let widest = $derived(item.colspan === field.columns);
	let narrowest = $derived(item.colspan === field.minCellWidth);
	let highest = $derived(item.rowspan === field.columns * 2);
	let onerow = $derived(item.rowspan === 1);
	let unindented = $derived(item.colstart === null);
	let fullyindented = $derived(
		item.colstart !== null &&
			item.colstart !== undefined &&
			item.colstart + item.colspan - 1 === field.columns,
	);

	function width(val: number) {
		return () => {
			item.colspan = item.colspan + val;
			setDirty();
		};
	}

	function height(val: number) {
		return () => {
			item.rowspan = item.rowspan + val;
			setDirty();
		};
	}

	function indent(val: number) {
		return () => {
			let colstart = item.colstart;

			if (val > 0 && colstart === null) {
				item.colstart = 2;
				setDirty();
				return;
			}

			if (colstart !== null && colstart !== undefined) {
				colstart += val;
			}

			if (colstart === 0) {
				colstart = null;
			}

			item.colstart = colstart;
			setDirty();
		};
	}
</script>

<div
	class="cms-blocks-size-buttons"
	class:cms-blocks-size-buttons-inline={!dropdown}
	class:cms-blocks-size-buttons-dropdown={dropdown}
>
	<button class="width-plus" disabled={widest} onclick={width(1)}>
		<span class="icon">
			<IcoExpand />
		</span>
		<BlockButtonLabel value={item.colspan} />
	</button>
	<button class="width-minus" disabled={narrowest} onclick={width(-1)}>
		<span class="icon">
			<IcoCollapse />
		</span>
		<BlockButtonLabel value={item.colspan} />
	</button>
	<button class="indent" disabled={fullyindented} onclick={indent(1)}>
		<IcoIndent />
		<BlockButtonLabel value={item.colstart ?? null} />
	</button>
	<button class="unindent" disabled={unindented} onclick={indent(-1)}>
		<IcoUnindent />
		<BlockButtonLabel value={item.colstart ?? null} />
	</button>
	<button class="height-plus" disabled={highest} onclick={height(1)}>
		<IcoExpand />
		<BlockButtonLabel value={item.rowspan} />
	</button>
	<button class="height-minus" disabled={onerow} onclick={height(-1)}>
		<IcoCollapse />
		<BlockButtonLabel value={item.rowspan} />
	</button>
</div>

<style lang="postcss">
	.cms-blocks-size-buttons {
		display: flex;
		flex: 1 1 auto;
		flex-direction: row;
		align-items: center;
		gap: var(--cms-space-3);
		padding: var(--cms-space-2) 0;
	}

	.cms-blocks-size-buttons-inline {
		justify-content: flex-start;
	}

	.cms-blocks-size-buttons-dropdown {
		justify-content: center;
	}

	div button {
		position: relative;
		height: var(--cms-space-4);
		width: var(--cms-space-4);

		&[disabled] {
			color: var(--cms-color-neutral-300);
		}
	}

	.width-minus,
	.width-plus {
		span.icon {
			display: block;
			transform: rotate(90deg);
		}
	}
</style>
