<script lang="ts">
	import type { FileItem, LocaleMap } from '$types/data';

	import { untrack } from 'svelte';
	import { ZXX } from '$types/data';
	import { system, systemLocale } from '$lib/sys';
	import { pruneItemMeta } from '$lib/content';
	import { __ } from '$lib/locale';
	import LocaleTabs from '$components/LocaleTabs.svelte';

	type Props = {
		item: FileItem;
		translate: boolean;
		// Receives the item with pruned meta on every edit, so empty
		// texts never shadow the asset's catalog defaults.
		update: (item: FileItem) => void;
	};

	let { item, translate, update }: Props = $props();

	const id = $props.id();

	// Editing scaffold seeded once — the parent keys this component on
	// the asset uid, so a replaced image starts from its own meta.
	let alt: LocaleMap<string> = $state(untrack(() => ({ ...(item.meta?.alt ?? {}) })));
	let title: LocaleMap<string> = $state(untrack(() => ({ ...(item.meta?.title ?? {}) })));
	let lang = $state(systemLocale($system));
	let keys = $derived(translate ? $system.locales.map((locale) => locale.id) : [ZXX]);

	function commit() {
		update(
			pruneItemMeta({
				...item,
				meta: { ...item.meta, alt: $state.snapshot(alt), title: $state.snapshot(title) },
			}),
		);
	}
</script>

<div class="cms-media-meta">
	<div class="entry">
		<label class="caption" for="{id}-alt">
			<span>{__('image:alt-text')}</span>
			{#if translate}
				<LocaleTabs bind:lang />
			{/if}
		</label>
		{#each keys as key (key)}
			{#if !translate || key === lang}
				<input
					class="cms-input"
					id="{id}-alt"
					type="text"
					autocomplete="off"
					placeholder={__('image:alt-text-placeholder')}
					bind:value={alt[key]}
					oninput={commit}
				/>
			{/if}
		{/each}
		<span class="help">{__('image:alt-text-hint')}</span>
	</div>
	<div class="entry">
		<label class="caption" for="{id}-title">
			<span>{__('common:title')}</span>
			{#if translate}
				<LocaleTabs bind:lang />
			{/if}
		</label>
		{#each keys as key (key)}
			{#if !translate || key === lang}
				<input
					class="cms-input"
					id="{id}-title"
					type="text"
					autocomplete="off"
					placeholder={__('common:optional')}
					bind:value={title[key]}
					oninput={commit}
				/>
			{/if}
		{/each}
	</div>
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
				color: var(--cms-color-text-muted);
				font-size: var(--cms-font-size-xs);
				font-weight: 600;
				line-height: 1.25rem;
			}

			& .help {
				color: var(--cms-color-text-subtle);
				font-size: var(--cms-font-size-xs);
				line-height: 1.45;
			}
		}
	}
</style>
