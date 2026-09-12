<script lang="ts">
	import type { FileItem } from '$types/data';

	import { useAssets } from '$lib/assets';
	import { __ } from '$lib/locale';
	import { portal } from '$lib/portal';
	import Icon from '$components/Icon.svelte';
	import ContentLocales from '$components/ContentLocales.svelte';
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
		allowed,
		settings,
		update,
		remove,
		upload,
		library,
		readonly = false,
	}: Props = $props();

	const assets = useAssets();

	let info = $derived(item?.uid ? $assets[item.uid] : undefined);
	let filename = $derived(info?.filename ?? item?.uid ?? '');
</script>

<div class="cms-video-figure">
	{#if item}
		<figure>
			<div class="frame">
				<!-- svelte-ignore a11y_media_has_caption -->
				<video controls preload="metadata" src={info?.url ?? ''}></video>
				<!-- Along the top edge: the player's own controls own the bottom. -->
				<div class="overlay">
					<span class="filename" title={filename}
						>{loading ? __('upload:uploading') : filename}</span
					>
					{#if !readonly}
						<button type="button" class="quiet" onclick={upload}>{__('image:replace')}</button>
						<button type="button" class="quiet" onclick={library}>
							{__('media:choose-from-library')}
						</button>
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
	{:else}
		<div class="dropzone">
			<Icon name="cloud-upload" />
			<span class="prompt">{loading ? __('upload:uploading') : __('upload:drop-video')}</span>
			<span class="facts">{allowed}</span>
			{#if !readonly}
				<span class="tools">
					<button type="button" class="cms-button secondary small" onclick={upload}>
						{__('video:upload')}
					</button>
					<button type="button" class="textlink" onclick={library}>
						{__('media:choose-from-library')}
					</button>
				</span>
			{/if}
		</div>
	{/if}
</div>

<style>
	@layer panel {
		.cms-video-figure {
			& figure {
				margin: 0;
			}

			& .frame {
				position: relative;
				border-radius: var(--cms-radius-sm);
				overflow: hidden;
				background: var(--cms-color-neutral-900);
			}

			& video {
				display: block;
				width: 100%;
				max-height: 32rem;
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
