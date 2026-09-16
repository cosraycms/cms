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
	import ReplaceMenu from './ReplaceMenu.svelte';

	type Props = {
		item: FileItem;
		loading: boolean;
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		update: (item: FileItem) => void;
		remove: () => void;
		upload: () => void;
		library: () => void;
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
		upload,
		library,
		readonly = false,
	}: Props = $props();

	const assets = useAssets();
	let root = $state<HTMLElement>();

	let info = $derived(item.uid ? $assets[item.uid] : undefined);
	let filename = $derived(info?.filename ?? item.uid ?? '');
	let thumb = $derived(info?.thumbUrl ?? info?.url ?? '');
	let facts = $derived(info ? assetLine(info).split(' · ') : []);

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

<div class="cms-image-card" class:is-readonly={readonly} bind:this={root}>
	<div class="overview">
		<button type="button" class="thumb" title={__('common:preview')} onclick={preview}>
			{#if thumb}
				<img src={thumb} alt="" />
			{:else}
				<span class="plate">{extension(filename)}</span>
			{/if}
		</button>
		<div class="details">
			<span class="filename" title={filename}>{filename}</span>
			<span class="facts">
				{#if loading}
					{__('upload:uploading')}
				{:else}
					{#each facts as fact, index (index)}
						<span>{fact}{index < facts.length - 1 ? ' ·' : ''}</span>{' '}
					{/each}
				{/if}
			</span>
		</div>
		{#if !readonly}
			<div class="controls">
				<ReplaceMenu {upload} {library} />
				<button
					type="button"
					class="discard cms-button secondary small"
					title={__('common:remove')}
					aria-label={__('common:remove')}
					onclick={remove}
				>
					<Icon name="x-lg" />
				</button>
			</div>
		{/if}
	</div>
	<div class="descriptions">
		{#key `${identity}:${item.uid}`}
			<MetaFields {item} {translate} {contentLocale} {locales} {update} {readonly} />
		{/key}
	</div>
</div>

<style>
	@layer panel {
		.cms-image-card {
			& .overview {
				display: flex;
				align-items: center;
				gap: var(--cms-space-3);
				padding: var(--cms-space-3);
			}

			& .thumb {
				position: relative;
				display: grid;
				place-items: center;
				width: 4.5rem;
				height: 4.5rem;
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

				&:focus-visible {
					outline: var(--cms-focus-outline);
					outline-offset: var(--cms-focus-offset);
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
				flex: 1 1 auto;
				flex-direction: column;
				gap: var(--cms-space-0-5);
				min-width: 0;
			}

			& .filename {
				overflow: hidden;
				font-size: var(--cms-font-size-sm);
				font-weight: 600;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			& .facts {
				min-height: 1.25rem;
				font-size: var(--cms-font-size-xs);
				color: var(--cms-color-text-subtle);
				font-variant-numeric: tabular-nums;

				/* Wraps between the facts, and inside one only when it cannot fit a line. */
				& span {
					display: inline-block;
					max-width: 100%;
				}
			}

			& .controls {
				display: flex;
				flex-shrink: 0;
				align-items: center;
				gap: var(--cms-space-2);
			}

			& .discard {
				width: var(--cms-control-height-sm);
				padding-inline: 0;

				& :global(svg) {
					width: 0.75rem;
					height: 0.75rem;
				}
			}

			/* The frame's radius less its border, so the band fills the corners. */
			& .descriptions {
				padding: var(--cms-space-3);
				border-top: 1px solid var(--cms-color-border);
				border-radius: 0 0 calc(var(--cms-radius-md) - 1px) calc(var(--cms-radius-md) - 1px);
				background: var(--cms-color-surface-muted);
			}

			&.is-readonly .descriptions {
				background: none;
			}
		}
	}
</style>
