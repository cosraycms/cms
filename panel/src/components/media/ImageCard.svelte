<script lang="ts">
	import type { FileItem } from '$types/data';

	import { mount, unmount } from 'svelte';
	import { cosray } from '$lib/bridge';
	import { useAssets } from '$lib/assets';
	import { assetLine, extension } from '$lib/library';
	import { __ } from '$lib/locale';
	import IcoUpload from '$components/icons/IcoUpload.svelte';
	import ImagePreview from '$components/ImagePreview.svelte';
	import MetaFields from './MetaFields.svelte';

	type Props = {
		item: FileItem | null;
		loading: boolean;
		translate: boolean;
		allowed: string;
		update: (item: FileItem) => void;
		remove: () => void;
		upload: () => void;
		library: () => void;
	};

	let { item, loading, translate, allowed, update, remove, upload, library }: Props = $props();

	const assets = useAssets();

	let info = $derived(item?.uid ? $assets[item.uid] : undefined);
	let filename = $derived(info?.filename ?? item?.uid ?? '');
	let thumb = $derived(info?.thumbUrl ?? info?.url ?? '');
	let line = $derived(info ? assetLine(info) : '');

	function preview() {
		const image = info?.previewUrl ?? info?.url;

		if (!image) {
			return;
		}

		const handle = cosray().modal.open((host) => {
			const app = mount(ImagePreview, {
				target: host,
				props: { image, close: () => handle.close() },
			});

			return () => void unmount(app);
		});
	}
</script>

<div class="cms-image-card">
	{#if item}
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
				<span class="tools">
					<button type="button" class="quiet" onclick={upload}>{__('image:replace')}</button>
					<button type="button" class="quiet" onclick={library}>
						{__('media:choose-from-library')}
					</button>
					<button type="button" class="quiet" onclick={remove}>{__('common:remove')}</button>
				</span>
			</div>
			<div class="facts">{loading ? __('upload:uploading') : line}</div>
			{#key item.uid}
				<MetaFields {item} {translate} {update} />
			{/key}
		</div>
	{:else}
		<div class="thumb placeholder"><IcoUpload /></div>
		<div class="details">
			<div class="prompt">{loading ? __('upload:uploading') : __('upload:drop-image')}</div>
			<div class="facts">{allowed}</div>
			<div class="tools">
				<button type="button" class="cms-button secondary small" onclick={upload}>
					{__('image:upload')}
				</button>
				<button type="button" class="textlink" onclick={library}>
					{__('media:choose-from-library')}
				</button>
			</div>
		</div>
	{/if}
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
				width: 11.25rem;
				max-width: 100%;
				aspect-ratio: 4 / 3;
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

				&.placeholder {
					border: 1px dashed var(--cms-color-border-strong);
					color: var(--cms-color-text-faint);
					cursor: default;

					& :global(svg) {
						width: var(--cms-space-5);
						height: var(--cms-space-5);
					}
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
				flex-wrap: wrap;
				align-items: baseline;
				gap: var(--cms-space-1) var(--cms-space-2-5);
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

			& .tools {
				display: flex;
				flex-shrink: 0;
				align-items: center;
				gap: var(--cms-space-0-5);
				margin-left: auto;
			}

			& .quiet {
				padding: var(--cms-space-0-5) var(--cms-space-1-5);
				border: 0;
				border-radius: var(--cms-radius-md);
				background: transparent;
				font-size: var(--cms-font-size-xs);
				font-weight: 500;
				color: var(--cms-color-text-muted);
				cursor: pointer;

				&:hover {
					background: var(--cms-color-hover);
					color: var(--cms-color-text);
				}
			}

			& .facts {
				min-height: 1.25rem;
				font-size: var(--cms-font-size-xs);
				color: var(--cms-color-text-faint);
				font-variant-numeric: tabular-nums;
			}

			& .prompt {
				font-size: var(--cms-font-size-sm);
				font-weight: 500;
				color: var(--cms-color-text-muted);
			}

			& .details > .tools {
				margin-left: 0;
				margin-top: var(--cms-space-0-5);
				gap: var(--cms-space-2);
			}

			& .textlink {
				padding: var(--cms-space-1-5) var(--cms-space-1);
				border: 0;
				background: transparent;
				font-size: var(--cms-font-size-xs);
				font-weight: 500;
				color: var(--cms-color-text-muted);
				text-decoration: underline;
				text-underline-offset: 3px;
				cursor: pointer;

				&:hover {
					color: var(--cms-color-text);
				}
			}
		}
	}
</style>
