<script lang="ts">
	import type { FileItem, UploadType } from '$types/data';

	import { useAssets } from '$lib/assets';
	import { extension } from '$lib/library';
	import FileRow from './FileRow.svelte';

	type Props = {
		items: FileItem[];
		type: UploadType;
		label: string;
		translate: boolean;
		contentLocale: string;
	};

	let { items, type, label, translate, contentLocale }: Props = $props();
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
				<FileRow {item} {translate} {contentLocale} inert />
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
			display: flex;
			flex-direction: column;
		}
	}
</style>
