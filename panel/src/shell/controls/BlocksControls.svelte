<script lang="ts">
	import type { Block } from '$types/data';
	import type { BlocksField } from '$types/fields';
	import BlockSizeButtons from '$shell/controls/BlockSizeButtons.svelte';
	import BlockCellButtons from '$shell/controls/BlockCellButtons.svelte';
	import IcoThreeDots from '$shell/icons/IcoThreeDots.svelte';
	import IcoGear from '$shell/icons/IcoGear.svelte';

	interface Props {
		data: Block[];
		item: Block;
		field: BlocksField;
		index: number;
		edit: () => void;
		add: () => void;
	}

	let {
		data = $bindable(),
		item = $bindable(),
		field = $bindable(),
		index = $bindable(),
		edit,
		add,
	}: Props = $props();

	let showDropdown = $state(false);
</script>

<div class="content-actions cms-blocks-controls">
	{#if (item.width ?? 0) < 350}
		<div class="cms-blocks-controls-compact">
			<div class="cms-blocks-buttons cms-blocks-buttons-dropdown">
				<div>
					<button
						type="button"
						class="cms-blocks-buttons-toggle"
						onclick={() => (showDropdown = !showDropdown)}
					>
						<span class="sr-only">Open options</span>
						<IcoThreeDots />
					</button>
				</div>
				{#if showDropdown}
					<div
						class="cms-blocks-buttons-menu"
						role="menu"
						aria-orientation="vertical"
						aria-labelledby="menu-button"
						tabindex="-1"
					>
						<div class="cms-blocks-buttons-menu-content" role="none">
							<BlockCellButtons bind:data bind:item bind:index {add} dropdown />
							<BlockSizeButtons bind:field bind:item dropdown />
						</div>
					</div>
				{/if}
			</div>
		</div>
	{:else}
		<div class="cms-blocks-buttons cms-blocks-buttons-inline">
			<BlockSizeButtons bind:field bind:item />
			<BlockCellButtons bind:data bind:item bind:index {add} />
		</div>
	{/if}
	<div class="cms-blocks-controls-edit">
		<button class="edit" onclick={edit}>
			<IcoGear />
		</button>
	</div>
</div>

<style lang="postcss">
	div button {
		height: var(--cms-space-4);
		width: var(--cms-space-4);
	}

	.cms-blocks-controls {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: flex-end;
	}

	.cms-blocks-controls-compact {
		display: flex;
		flex: 1 1 auto;
		flex-direction: row;
		align-items: center;
		justify-content: flex-end;
		gap: var(--cms-space-3);
		padding: var(--cms-space-2) 0;
		margin-right: var(--cms-space-3);
	}

	.cms-blocks-controls-edit {
		display: flex;
		flex: 0 1 auto;
		flex-direction: row;
		align-items: center;
		justify-content: flex-end;
	}

	.cms-blocks-buttons {
		opacity: 0;
		transition: opacity 0.35s ease;
	}

	.cms-blocks-buttons-dropdown {
		position: relative;
		display: inline-block;
		text-align: left;
		opacity: 1;
	}

	.cms-blocks-buttons-inline {
		display: flex;
		flex: 1 1 auto;
		flex-direction: row;
		align-items: center;
		justify-content: flex-end;
	}

	.cms-blocks-buttons-toggle {
		display: flex;
		align-items: center;
	}

	.cms-blocks-buttons-menu {
		position: absolute;
		right: 0;
		z-index: 10;
		margin-top: var(--cms-space-2);
		width: 11rem;
		transform-origin: top right;
		border-radius: var(--cms-radius-md);
		background-color: var(--cms-color-white);
		padding: 0 var(--cms-space-2);
		box-shadow: var(--cms-shadow-lg);
		outline: none;
		border: 1px solid color-mix(in srgb, var(--cms-color-black) 5%, transparent);
	}

	.cms-blocks-buttons-menu-content {
		display: flex;
		flex-direction: column;
		justify-content: center;
		padding: var(--cms-space-1) 0;
	}

	.cms-blocks-buttons:hover {
		opacity: 1;
	}

	.cms-blocks-buttons :global(button .block-button-label) {
		opacity: 0;
	}

	.cms-blocks-buttons :global(button:hover .block-button-label) {
		opacity: 1;
	}

	.edit {
		opacity: 1;
	}
</style>
