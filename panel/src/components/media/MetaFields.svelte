<script lang="ts">
	import type { FileItem, LocaleMap, UploadType } from '$types/data';

	import { untrack } from 'svelte';
	import { ZXX } from '$lib/content';
	import { useAssets } from '$lib/assets';
	import { pruneItemMeta } from '$lib/content';
	import { localeTitle, resolveTextFallback } from '$lib/fallback';
	import { __ } from '$lib/locale';

	type Key = 'alt' | 'caption' | 'title';

	type Props = {
		item: FileItem;
		// Alt text describes what an image shows; a caption is visible text
		// under an image or video; a file's title is its display name.
		kind?: UploadType;
		translate: boolean;
		contentLocale: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		// Receives the item with pruned meta on every edit, so empty
		// texts never shadow the asset's catalog defaults.
		update: (item: FileItem) => void;
		readonly?: boolean;
	};

	let {
		item,
		kind = 'image',
		translate,
		contentLocale,
		locales,
		update,
		readonly = false,
	}: Props = $props();

	const id = $props.id();
	const keys: Key[] = $derived(
		kind === 'image' ? ['alt', 'caption'] : kind === 'video' ? ['caption'] : ['title'],
	);
	const labels: Record<Key, string> = {
		alt: __('image:alt-text'),
		caption: __('image:caption'),
		title: __('common:title'),
	};
	const notes: Record<Key, string> = {
		alt: __('image:alt-text-note'),
		caption: __('field:optional'),
		title: __('field:optional'),
	};

	// Editing scaffold seeded once — the parent keys this component on
	// the asset uid, so a replaced image starts from its own meta.
	let texts: Record<string, LocaleMap<string>> = $state(
		untrack(() => Object.fromEntries(keys.map((key) => [key, { ...(item.meta?.[key] ?? {}) }]))),
	);
	let focused: Key | null = $state(null);
	const assets = useAssets();
	let key = $derived(translate ? contentLocale : ZXX);
	// A neutral value reads its catalog text in the site's default locale,
	// the nearest the panel has to the page locale the site resolves with.
	let catalogLocale = $derived(translate ? contentLocale : (locales?.default ?? contentLocale));
	let catalog = $derived(item.uid ? $assets[item.uid]?.meta : undefined);

	function fallback(name: Key) {
		return resolveTextFallback(
			texts[name],
			catalog?.[name] as LocaleMap<string> | undefined,
			key,
			locales?.all ?? [],
			catalogLocale,
		);
	}

	function sourceLabel(source: string): string {
		const language =
			source === ZXX ? __('field:shared-content') : localeTitle(locales?.all ?? [], source);

		return __('field:fallback-from', { language });
	}

	function placeholder(name: Key): string {
		const resolved = focused === name ? null : fallback(name);

		if (resolved) {
			return resolved.value;
		}

		if (name === 'alt') {
			return __('image:alt-text-placeholder');
		}

		if (name === 'title') {
			return __('media:title-placeholder');
		}

		return kind === 'video' ? __('video:caption-placeholder') : __('image:caption-placeholder');
	}

	function commit() {
		update(pruneItemMeta({ ...item, meta: { ...item.meta, ...$state.snapshot(texts) } }));
	}
</script>

<div class="cms-media-meta">
	{#each keys as name (name)}
		{@const resolved = focused === name ? null : fallback(name)}
		<div class="entry">
			<label class="caption" for="{id}-{name}">
				{labels[name]}
				<span class="remark">{notes[name]}</span>
			</label>
			{#if name === 'caption'}
				<textarea
					class="cms-textarea"
					id="{id}-{name}"
					rows="1"
					{readonly}
					placeholder={placeholder(name)}
					bind:value={texts[name][key]}
					onfocus={() => (focused = name)}
					onblur={() => (focused = null)}
					oninput={commit}
				></textarea>
			{:else}
				<input
					class="cms-input"
					id="{id}-{name}"
					type="text"
					autocomplete="off"
					{readonly}
					placeholder={placeholder(name)}
					bind:value={texts[name][key]}
					onfocus={() => (focused = name)}
					onblur={() => (focused = null)}
					oninput={commit}
				/>
			{/if}
			{#if resolved}
				<span class="fallback">{sourceLabel(resolved.locale)}</span>
			{/if}
		</div>
	{/each}
</div>

<style>
	@layer panel {
		.cms-media-meta {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
			gap: var(--cms-space-3);

			& .entry {
				display: flex;
				flex-direction: column;
				gap: var(--cms-space-1-5);
				min-width: 0;
			}

			& .caption {
				display: flex;
				flex-wrap: wrap;
				align-items: baseline;
				gap: 0 var(--cms-space-1-5);
				color: var(--cms-color-text-label);
				font-size: var(--cms-font-size-sm);
				font-weight: 600;
				line-height: 1.25rem;
			}

			& .remark {
				color: var(--cms-color-text-subtle);
				font-size: var(--cms-font-size-xs);
				font-weight: 400;
			}

			/* One line, level with the input beside it, growing with its text
			   where the browser can size a field to its content. */
			& .cms-textarea {
				min-height: var(--cms-control-height);
				padding-block: calc((var(--cms-control-height) - 1.5rem - 2px) / 2);
				line-height: 1.5rem;
				resize: vertical;

				@supports (field-sizing: content) {
					field-sizing: content;
					resize: none;
				}
			}

			& .fallback {
				color: var(--cms-color-text-subtle);
				font-size: var(--cms-font-size-xs);
				line-height: 1.45;
			}

			& .fallback {
				font-style: italic;
			}
		}
	}
</style>
