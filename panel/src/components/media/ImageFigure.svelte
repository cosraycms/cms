<script lang="ts">
	import type { FileItem, LocaleMap } from '$types/data';

	import { ZXX } from '$types/data';
	import { useAssets } from '$lib/assets';
	import { filled, resolveTextFallback } from '$lib/fallback';
	import { extension } from '$lib/library';
	import { __ } from '$lib/locale';
	import { portal } from '$lib/portal';
	import Icon from '$components/Icon.svelte';
	import MetaFields from './MetaFields.svelte';

	type Props = {
		item: FileItem | null;
		loading: boolean;
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		allowed: string;
		// Where the per-use form goes: a settings dialog's slot when the
		// owner offers one, below the image otherwise.
		settings?: HTMLElement;
		update: (item: FileItem) => void;
		remove: () => void;
		upload: () => void;
		library: () => void;
	};

	let {
		item,
		loading,
		translate,
		contentLocale,
		identity,
		locales,
		allowed,
		settings,
		update,
		remove,
		upload,
		library,
	}: Props = $props();

	const assets = useAssets();

	let info = $derived(item?.uid ? $assets[item.uid] : undefined);
	let filename = $derived(info?.filename ?? item?.uid ?? '');
	let src = $derived(info?.previewUrl ?? info?.url ?? '');
	let key = $derived(translate ? contentLocale : ZXX);
	// A neutral value reads its catalog text in the site's default locale,
	// the nearest the panel has to the page locale the site resolves with.
	let catalogLocale = $derived(translate ? contentLocale : (locales?.default ?? contentLocale));
	let caption = $derived(item ? effectiveCaption(item) : '');

	// The caption as the site will render it: per use, else the catalog's.
	function effectiveCaption(item: FileItem): string {
		const own = item.meta?.caption as LocaleMap<string> | undefined;

		if (filled(own?.[key])) {
			return own?.[key] ?? '';
		}

		return (
			resolveTextFallback(
				own,
				info?.meta?.caption as LocaleMap<string> | undefined,
				key,
				locales?.all ?? [],
				catalogLocale,
			)?.value ?? ''
		);
	}
</script>

<div class="cms-image-figure">
	{#if item}
		<figure>
			<div class="frame">
				{#if src}
					<img {src} alt="" />
				{:else}
					<span class="plate">{extension(filename)}</span>
				{/if}
				<div class="overlay">
					<span class="filename" title={filename}
						>{loading ? __('upload:uploading') : filename}</span
					>
					<button type="button" class="quiet" onclick={upload}>{__('image:replace')}</button>
					<button type="button" class="quiet" onclick={library}>
						{__('media:choose-from-library')}
					</button>
					<button type="button" class="quiet" onclick={remove}>{__('common:remove')}</button>
				</div>
			</div>
			{#if caption}
				<figcaption>{caption}</figcaption>
			{/if}
		</figure>
		{#key `${identity}:${item.uid}`}
			<div class="cms-figure-settings" use:portal={settings}>
				<MetaFields {item} kind="image" {translate} {contentLocale} {locales} {update} />
			</div>
		{/key}
	{:else}
		<div class="dropzone">
			<Icon name="cloud-upload" />
			<span class="prompt">{loading ? __('upload:uploading') : __('upload:drop-image')}</span>
			<span class="facts">{allowed}</span>
			<span class="tools">
				<button type="button" class="cms-button secondary small" onclick={upload}>
					{__('image:upload')}
				</button>
				<button type="button" class="textlink" onclick={library}>
					{__('media:choose-from-library')}
				</button>
			</span>
		</div>
	{/if}
</div>

<style>
	@layer panel {
		.cms-image-figure {
			& figure {
				margin: 0;
			}

			& .frame {
				position: relative;
				border-radius: var(--cms-radius-sm);
				overflow: hidden;
			}

			& img {
				display: block;
				width: 100%;
				height: auto;
			}

			& .plate {
				display: grid;
				place-items: center;
				aspect-ratio: 4 / 3;
				background: var(--cms-color-surface);
				font-size: var(--cms-font-size-xs);
				letter-spacing: 0.08em;
				text-transform: uppercase;
				color: var(--cms-color-text-subtle);
			}

			/* Along the image's bottom edge, only while the figure is hovered
			   or holds focus; unclickable while hidden so the image's own
			   ground keeps its click. */
			& .overlay {
				position: absolute;
				inset-inline: 0;
				inset-block-end: 0;
				display: flex;
				align-items: center;
				gap: var(--cms-space-0-5);
				padding: var(--cms-space-1-5) var(--cms-space-2);
				background: color-mix(in srgb, var(--cms-color-surface) 88%, transparent);
				opacity: 0;
				pointer-events: none;
				transition: opacity 120ms ease;
			}

			& figure:hover .overlay,
			& figure:focus-within .overlay {
				opacity: 1;
				pointer-events: auto;
			}

			& .filename {
				flex: 1 1 auto;
				min-width: 0;
				overflow: hidden;
				font-size: var(--cms-font-size-xs);
				color: var(--cms-color-text-muted);
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			& .quiet {
				flex-shrink: 0;
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

			& figcaption {
				margin-top: var(--cms-space-2);
				font-size: var(--cms-font-size-sm);
				line-height: 1.5;
				color: var(--cms-color-text-muted);
			}

			& .cms-figure-settings {
				margin-top: var(--cms-space-3);
			}

			& .dropzone {
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: var(--cms-space-2);
				padding: var(--cms-space-8) var(--cms-space-4);
				border: 1px dashed var(--cms-color-border-strong);
				border-radius: var(--cms-radius-md);
				text-align: center;
				color: var(--cms-color-text-subtle);

				& :global(svg) {
					width: var(--cms-space-5);
					height: var(--cms-space-5);
					color: var(--cms-color-text-faint);
				}
			}

			& .prompt {
				font-size: var(--cms-font-size-sm);
				font-weight: 500;
				color: var(--cms-color-text-muted);
			}

			& .facts {
				font-size: var(--cms-font-size-xs);
				color: var(--cms-color-text-faint);
			}

			& .tools {
				display: flex;
				align-items: center;
				gap: var(--cms-space-2);
				margin-top: var(--cms-space-1);
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
