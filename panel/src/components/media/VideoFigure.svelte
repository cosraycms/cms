<script lang="ts">
	import type { FileItem } from '$types/data';

	import { useAssets } from '$lib/assets';
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
		// owner offers one, below the player otherwise.
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
</script>

<div class="cms-video-figure">
	<figure>
		<div class="frame">
			<!-- svelte-ignore a11y_media_has_caption -->
			<video controls preload="metadata" src={info?.url ?? ''}></video>
			<!-- Along the top edge: the player's own controls own the bottom. -->
			<div class="overlay">
				<span class="filename" title={filename}>{loading ? __('upload:uploading') : filename}</span>
				{#if !readonly}
					<ReplaceMenu quiet {upload} {library} />
					<button type="button" class="quiet" onclick={remove}>{__('common:remove')}</button>
				{/if}
			</div>
		</div>
	</figure>
	{#key `${identity}:${item.uid}`}
		<div class="cms-figure-settings" use:portal={settings}>
			{#if settings && translate && locales && locales.all.length > 1}
				<ContentLocales locales={locales.all} locale={contentLocale} />
			{/if}
			<MetaFields {item} kind="video" {translate} {contentLocale} {locales} {update} {readonly} />
		</div>
	{/key}
</div>

<style>
	@layer panel {
		.cms-video-figure {
			& figure {
				margin: 0;
			}

			& .frame {
				position: relative;
				/* Clipped without overflow, which would also confine the replace
				   menu's placement to the frame. */
				clip-path: inset(0 round var(--cms-radius-sm));
				background: var(--cms-color-neutral-900);
			}

			/* The same 30rem line as the image block; a portrait clip letterboxes. */
			& video {
				display: block;
				width: 100%;
				max-height: 30rem;
			}

			& .overlay {
				position: absolute;
				inset-inline: 0;
				inset-block-start: 0;
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

			& .cms-figure-settings {
				margin-top: var(--cms-space-3);
			}
		}
	}
</style>
