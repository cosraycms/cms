<script lang="ts">
	import type { LibraryItem } from '$lib/library';

	import { humanSize } from '$lib/library';

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

	function ext(filename: string): string {
		const dot = filename.lastIndexOf('.');

		return dot === -1 ? '' : filename.slice(dot + 1, dot + 6).toUpperCase();
	}

	function metaLine(item: LibraryItem): string {
		const parts: string[] = [];

		if (item.width && item.height) {
			parts.push(`${item.width} × ${item.height}`);
		} else {
			const suffix = ext(item.filename);

			if (suffix !== '') {
				parts.push(suffix);
			}
		}

		if (typeof item.bytes === 'number') {
			parts.push(humanSize(item.bytes));
		}

		return parts.join(' · ');
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
					<span class="cms-asset-ext">{ext(item.filename) || item.kind}</span>
				{/if}
			</span>
			<span class="cms-asset-name">{item.filename}</span>
			{#if metaLine(item) !== ''}
				<span class="cms-asset-line">{metaLine(item)}</span>
			{/if}
		</button>
	{/each}
</div>

<style>
	@layer panel {
		.cms-asset-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(var(--cms-tile-min, 9.5rem), 1fr));
			gap: var(--cms-space-3);
			align-content: start;
		}

		.cms-asset-tile {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-1);
			padding: 0;
			border: 0;
			background: none;
			cursor: pointer;
			text-align: left;
			min-width: 0;
		}

		.cms-asset-thumb {
			display: flex;
			align-items: center;
			justify-content: center;
			aspect-ratio: 4 / 3;
			width: 100%;
			border: 1px solid var(--cms-color-border-strong);
			border-radius: var(--cms-radius-md);
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

		.cms-asset-tile.active .cms-asset-thumb {
			border-color: var(--cms-color-info);
			outline: 2px solid var(--cms-color-info);
		}

		.cms-asset-name {
			font-size: var(--cms-font-size-sm);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			max-width: 100%;
		}

		.cms-asset-line {
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-text-subtle);
			font-variant-numeric: tabular-nums;
		}
	}
</style>
