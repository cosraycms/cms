<script lang="ts">
	import type { FileItem } from '$types/data';

	import { mount, unmount } from 'svelte';
	import { cosray } from '$lib/bridge';
	import { useAssets } from '$lib/assets';
	import { assetLine, extension } from '$lib/library';
	import { __ } from '$lib/locale';
	import Icon from '$components/Icon.svelte';
	import ImagePreview from '$components/ImagePreview.svelte';
	import MetaFields from './MetaFields.svelte';

	type Props = {
		item: FileItem;
		loading: boolean;
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		update: (item: FileItem) => void;
		remove: () => void;
		readonly?: boolean;
	};

	let {
		item,
		loading,
		translate,
		contentLocale,
		identity,
		locales,
		update,
		remove,
		readonly = false,
	}: Props = $props();

	const assets = useAssets();
	let root = $state<HTMLElement>();

	let info = $derived(item.uid ? $assets[item.uid] : undefined);
	let filename = $derived(info?.filename ?? item.uid ?? '');
	let thumb = $derived(info?.thumbUrl ?? info?.url ?? '');
	let line = $derived(info ? assetLine(info) : '');

	function preview() {
		const image = info?.previewUrl ?? info?.url;

		if (!image) {
			return;
		}

		cosray().modal.open(
			(host) => {
				const app = mount(ImagePreview, {
					target: host,
					props: { image },
				});

				return () => void unmount(app);
			},
			{ owner: root, size: 'wide' },
		);
	}
</script>

<div class="cms-image-card" bind:this={root}>
	<button type="button" class="thumb" title={__('common:preview')} onclick={preview}>
		{#if thumb}
			<img src={thumb} alt="" />
		{:else}
			<span class="plate">{extension(filename)}</span>
		{/if}
	</button>
	<div class="details">
		<div class="filerow">
			<span class="filename" title={filename}>{filename}</span>
			{#if !readonly}
				<button
					type="button"
					class="discard"
					title={__('common:remove')}
					aria-label={__('common:remove')}
					onclick={remove}
				>
					<Icon name="x-lg" />
				</button>
			{/if}
		</div>
		<div class="facts">{loading ? __('upload:uploading') : line}</div>
		{#key `${identity}:${item.uid}`}
			<MetaFields {item} {translate} {contentLocale} {locales} {update} {readonly} />
		{/key}
	</div>
</div>

<style>
	@layer panel {
		.cms-image-card {
			display: flex;
			flex-wrap: wrap;
			align-items: flex-start;
			gap: var(--cms-space-3-5);
			padding: var(--cms-space-4);

			& .thumb {
				position: relative;
				display: grid;
				place-items: center;
				width: 12rem;
				max-width: 100%;
				height: 9rem;
				flex-shrink: 0;
				padding: 0;
				border: 0;
				border-radius: var(--cms-radius-md);
				background: var(--cms-color-surface-sunken);
				overflow: hidden;
				cursor: zoom-in;

				& img {
					position: absolute;
					inset: 0;
					width: 100%;
					height: 100%;
					object-fit: contain;
				}
			}

			& .plate {
				font-size: var(--cms-font-size-xs);
				letter-spacing: 0.08em;
				text-transform: uppercase;
				color: var(--cms-color-text-subtle);
			}

			& .details {
				display: flex;
				flex: 1 1 14rem;
				flex-direction: column;
				gap: var(--cms-space-2);
				min-width: 0;
			}

			& .filerow {
				display: flex;
				align-items: center;
				gap: var(--cms-space-2);
			}

			& .filename {
				flex: 1 1 8rem;
				min-width: 0;
				font-size: var(--cms-font-size-sm);
				font-weight: 500;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			& .discard {
				display: grid;
				flex-shrink: 0;
				place-items: center;
				width: 1.75rem;
				height: 1.75rem;
				margin-left: auto;
				padding: 0;
				border: 0;
				border-radius: var(--cms-radius);
				background: transparent;
				color: var(--cms-color-text-muted);
				cursor: pointer;

				& :global(svg) {
					width: 0.875rem;
					height: 0.875rem;
				}

				&:hover {
					background: var(--cms-color-hover);
					color: var(--cms-color-text);
				}

				&:focus-visible {
					outline: var(--cms-focus-outline);
					outline-offset: var(--cms-focus-offset);
				}
			}

			& .facts {
				min-height: 1.25rem;
				font-size: var(--cms-font-size-xs);
				color: var(--cms-color-text-faint);
				font-variant-numeric: tabular-nums;
			}
		}
	}
</style>
