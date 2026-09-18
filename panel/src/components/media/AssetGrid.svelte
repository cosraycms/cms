<script lang="ts">
	import type { LibraryItem } from '$lib/library';

	import { assetLine, extension } from '$lib/library';

	type Props = {
		items: LibraryItem[];
		// Matches an item by uid or url: pickers track either, depending on
		// what the field value stores.
		selected?: string | null;
		pick: (item: LibraryItem) => void;
	};

	let { items, selected = null, pick }: Props = $props();

	function active(item: LibraryItem): boolean {
		return selected !== null && (selected === item.uid || selected === item.url);
	}
</script>

<div class="cms-asset-grid">
	{#each items as item (item.uid)}
		<button
			type="button"
			class="cms-asset-tile"
			class:active={active(item)}
			title={item.filename}
			onclick={() => pick(item)}
		>
			<span class="cms-asset-thumb">
				{#if item.kind === 'image'}
					<img src={item.thumbUrl} alt="" loading="lazy" />
				{:else}
					<span class="cms-asset-ext">{extension(item.filename) || item.kind}</span>
				{/if}
			</span>
			<span class="cms-asset-meta">
				<span class="cms-asset-name">{item.filename}</span>
				{#if assetLine(item) !== ''}
					<span class="cms-asset-line">{assetLine(item)}</span>
				{/if}
			</span>
		</button>
	{/each}
</div>

<style>
	@layer panel {
		.cms-asset-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(var(--cms-tile-min, 9.5rem), 1fr));
			gap: var(--gap, var(--cms-space-3));
			align-content: start;
		}

		.cms-asset-tile {
			display: flex;
			flex-direction: column;
			padding: 0;
			border: 1px solid var(--cms-color-border);
			border-radius: var(--cms-radius-lg);
			background: var(--cms-color-surface);
			box-shadow: var(--cms-shadow-raised);
			cursor: pointer;
			text-align: left;
			min-width: 0;
			overflow: hidden;
		}

		.cms-asset-tile:hover {
			border-color: var(--cms-color-border-strong);
		}

		.cms-asset-tile:focus-visible {
			outline: var(--cms-focus-outline);
			outline-offset: var(--cms-focus-offset);
		}

		.cms-asset-thumb {
			display: flex;
			align-items: center;
			justify-content: center;
			aspect-ratio: 1 / 1;
			width: 100%;
			border-bottom: 1px solid var(--cms-color-border);
			background-color: var(--cms-color-surface-sunken);
			overflow: hidden;
		}

		.cms-asset-thumb img {
			width: 100%;
			height: 100%;
			object-fit: cover;
		}

		.cms-asset-ext {
			font-size: var(--cms-font-size-xs);
			letter-spacing: 0.08em;
			text-transform: uppercase;
			color: var(--cms-color-text-subtle);
		}

		/* The ring sits outside the border, so both together read as one edge. */
		.cms-asset-tile.active {
			border-color: var(--cms-color-accent);
			box-shadow:
				0 0 0 1px var(--cms-color-accent),
				var(--cms-shadow-raised);
		}

		.cms-asset-meta {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-0-5);
			padding: var(--cms-space-2) var(--cms-space-3) var(--cms-space-3);
			min-width: 0;
		}

		.cms-asset-name {
			font-size: var(--cms-font-size-sm);
			font-weight: 500;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			max-width: 100%;
		}

		.cms-asset-line {
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-text-subtle);
			font-variant-numeric: tabular-nums;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			max-width: 100%;
		}
	}
</style>
