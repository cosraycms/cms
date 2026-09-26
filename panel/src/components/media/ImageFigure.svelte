<script lang="ts">
	import type { FileItem, LocaleMap } from '$types/data';

	import { ZXX } from '$lib/content';
	import { useAssets } from '$lib/assets';
	import { filled, resolveTextFallback } from '$lib/fallback';
	import { extension } from '$lib/library';
	import { __ } from '$lib/locale';
	import { portal } from '$lib/portal';
	import ContentLocales from '$components/ContentLocales.svelte';
	import MetaFields from './MetaFields.svelte';
	import ReplaceMenu from './ReplaceMenu.svelte';

	type Props = {
		item: FileItem;
		loading: boolean;
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		// Where the per-use form goes: a settings dialog's slot when the
		// owner offers one, below the image otherwise.
		settings?: HTMLElement;
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
		settings,
		update,
		remove,
		upload,
		library,
		readonly = false,
	}: Props = $props();

	const assets = useAssets();

	let info = $derived(item.uid ? $assets[item.uid] : undefined);
	let filename = $derived(info?.filename ?? item.uid ?? '');
	let src = $derived(info?.previewUrl ?? info?.url ?? '');
	let key = $derived(translate ? contentLocale : ZXX);
	// A neutral value reads its catalog text in the site's default locale,
	// the nearest the panel has to the page locale the site resolves with.
	let catalogLocale = $derived(translate ? contentLocale : (locales?.default ?? contentLocale));
	let caption = $derived(effectiveCaption(item));

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
	<figure>
		<div class="frame">
			{#if src}
				<img {src} alt="" />
			{:else}
				<span class="plate">{extension(filename)}</span>
			{/if}
			<div class="overlay">
				<span class="filename" title={filename}>{loading ? __('upload:uploading') : filename}</span>
				{#if !readonly}
					<ReplaceMenu quiet {upload} {library} />
					<button type="button" class="quiet" onclick={remove}>{__('common:remove')}</button>
				{/if}
			</div>
		</div>
		{#if caption}
			<figcaption>{caption}</figcaption>
		{/if}
	</figure>
	{#key `${identity}:${item.uid}`}
		<div class="cms-figure-settings" use:portal={settings}>
			{#if settings && translate && locales && locales.all.length > 1}
				<ContentLocales locales={locales.all} locale={contentLocale} />
			{/if}
			<MetaFields {item} kind="image" {translate} {contentLocale} {locales} {update} {readonly} />
		</div>
	{/key}
</div>

<style>
	@layer panel {
		.cms-image-figure {
			& figure {
				margin: 0;
			}

			& .frame {
				position: relative;
				/* Clipped without overflow, which would also confine the replace
				   menu's placement to the frame. */
				clip-path: inset(0 round var(--cms-radius-sm));
			}

			/* At block width, but no taller than 30rem: a portrait picture
			   letterboxes inside the block instead of taking the screen. */
			& img {
				display: block;
				width: 100%;
				height: auto;
				max-height: 20rem;
				object-fit: contain;
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
			& figure:focus-within .overlay,
			& figure:has(:popover-open) .overlay {
				opacity: 1;
				pointer-events: auto;
			}

			/* Opening the menu must not leave its anchor at zero opacity during the fade. */
			& figure:has(:popover-open) .overlay {
				transition: none;
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

			& :global(.quiet) {
				display: inline-flex;
				align-items: center;
				gap: var(--cms-space-1);
				flex-shrink: 0;
				padding: var(--cms-space-0-5) var(--cms-space-1-5);
				border: 0;
				border-radius: var(--cms-radius-md);
				background: transparent;
				font-size: var(--cms-font-size-xs);
				font-weight: 500;
				color: var(--cms-color-text-muted);
				cursor: pointer;

				&:hover,
				&:global([aria-expanded='true']) {
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
		}
	}
</style>
