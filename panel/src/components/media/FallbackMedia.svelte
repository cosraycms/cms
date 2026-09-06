<script lang="ts">
	import type { FileItem, UploadType } from '$types/data';

	import { useAssets } from '$lib/assets';
	import { assetLine, extension } from '$lib/library';
	import IcoDocument from '$components/icons/IcoDocument.svelte';

	type Props = {
		items: FileItem[];
		type: UploadType;
		label: string;
	};

	let { items, type, label }: Props = $props();
	const assets = useAssets();

	function filename(item: FileItem): string {
		return $assets[item.uid ?? '']?.filename ?? item.uid ?? '';
	}

	function source(item: FileItem): string {
		const info = $assets[item.uid ?? ''];

		return info?.thumbUrl ?? info?.url ?? '';
	}
</script>

<div class="cms-media-fallback" aria-label={label}>
	<div class="label">{label}</div>
	{#if type === 'image'}
		<div class="images">
			{#each items as item (item)}
				<div class="image" title={filename(item)}>
					{#if source(item)}
						<img src={source(item)} alt="" loading="lazy" />
					{:else}
						<span>{extension(filename(item))}</span>
					{/if}
				</div>
			{/each}
		</div>
	{:else if type === 'video'}
		{#each items as item (item)}
			<video controls preload="metadata">
				<track kind="captions" />
				<source src={$assets[item.uid ?? '']?.url ?? ''} />
			</video>
		{/each}
	{:else}
		<div class="files">
			{#each items as item (item)}
				<div class="file">
					<IcoDocument />
					<span>{filename(item)}</span>
					<small>{$assets[item.uid ?? ''] ? assetLine($assets[item.uid ?? '']) : ''}</small>
				</div>
			{/each}
		</div>
	{/if}
</div>

<style>
	@layer panel {
		.cms-media-fallback {
			display: grid;
			gap: var(--cms-space-2);
			margin-bottom: var(--cms-space-3);
			border: 1px solid var(--cms-color-border);
			border-radius: var(--cms-radius-md);
			background: var(--cms-color-surface-sunken);
			padding: var(--cms-space-3);
			color: var(--cms-color-text-subtle);
		}

		.label {
			font-size: var(--cms-font-size-xs);
			font-style: italic;
		}

		.images {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(6rem, 1fr));
			gap: var(--cms-space-2);
		}

		.image {
			display: grid;
			place-items: center;
			aspect-ratio: 4 / 3;
			overflow: hidden;
			border-radius: var(--cms-radius-sm);
			background: var(--cms-color-surface);
			font-size: var(--cms-font-size-xs);
			text-transform: uppercase;
		}

		.image img {
			width: 100%;
			height: 100%;
			object-fit: contain;
		}

		video {
			width: 100%;
			max-height: 24rem;
			background: var(--cms-color-neutral-900);
		}

		.files {
			display: grid;
			gap: var(--cms-space-2);
		}

		.file {
			display: grid;
			grid-template-columns: var(--cms-space-5) minmax(0, 1fr) auto;
			align-items: center;
			gap: var(--cms-space-2);
		}

		.file :global(svg) {
			width: var(--cms-space-4);
			height: var(--cms-space-4);
		}

		.file span {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}
</style>
