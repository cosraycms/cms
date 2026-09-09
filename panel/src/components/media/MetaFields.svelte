<script lang="ts">
	import type { FileItem, LocaleMap, UploadType } from '$types/data';

	import { untrack } from 'svelte';
	import { ZXX } from '$types/data';
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
	};

	let { item, kind = 'image', translate, contentLocale, locales, update }: Props = $props();

	const id = $props.id();
	const keys: Key[] = $derived(
		kind === 'image' ? ['alt', 'caption'] : kind === 'video' ? ['caption'] : ['title'],
	);
	const labels: Record<Key, string> = {
		alt: __('image:alt-text'),
		caption: __('image:caption'),
		title: __('common:title'),
	};

	// Editing scaffold seeded once — the parent keys this component on
	// the asset uid, so a replaced image starts from its own meta.
	let texts: Record<string, LocaleMap<string>> = $state(
		untrack(() => Object.fromEntries(keys.map((key) => [key, { ...(item.meta?.[key] ?? {}) }]))),
	);
	let focused: Key | null = $state(null);
	const assets = useAssets();
	let key = $derived(translate ? contentLocale : ZXX);
	let catalog = $derived(item.uid ? $assets[item.uid]?.meta : undefined);

	function fallback(name: Key) {
		return resolveTextFallback(
			texts[name],
			catalog?.[name] as LocaleMap<string> | undefined,
			key,
			locales?.all ?? [],
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

		return name === 'alt' ? __('image:alt-text-placeholder') : __('common:optional');
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
				<span>{labels[name]}</span>
			</label>
			{#if name === 'caption'}
				<textarea
					class="cms-textarea"
					id="{id}-{name}"
					rows="2"
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
			{#if name === 'alt'}
				<span class="help">{__('image:alt-text-hint')}</span>
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
				align-items: center;
				gap: var(--cms-space-2);
				color: var(--cms-color-text-label);
				font-size: var(--cms-font-size-sm);
				font-weight: 600;
				line-height: 1.25rem;
			}

			& .cms-textarea {
				min-height: 2lh;
				resize: vertical;
			}

			& .fallback,
			& .help {
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
