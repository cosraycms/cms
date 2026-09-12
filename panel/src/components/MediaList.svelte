<script lang="ts">
	import type { FileItem, UploadType } from '$types/data';
	import type { SortableEvent } from 'sortablejs';
	import Sortable from 'sortablejs';
	import { mount, onMount, unmount } from 'svelte';
	import { assetsContext, useAssets } from '$lib/assets';
	import { cosray } from '$lib/bridge';
	import { pruneItemMeta } from '$lib/content';
	import Video from '$components/Video.svelte';
	import File from '$components/File.svelte';
	import ModalEditImage from '$components/modals/ModalEditImage.svelte';

	type Props = {
		items: FileItem[];
		multiple: boolean;
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		type: UploadType;
		loading: boolean;
		remove: (index: number | null) => void;
		/** The field cannot change: no reordering, no per-item actions. */
		readonly?: boolean;
		notify?: () => void;
	};

	let {
		items = $bindable(),
		multiple,
		translate,
		contentLocale,
		identity,
		locales,
		type,
		loading,
		remove,
		readonly = false,
		notify = () => {},
	}: Props = $props();
	const assets = useAssets();
	let sorterElement: HTMLElement | undefined = $state();
	// The open dialog's props stay live, so a language chosen through its
	// mirrored selector reaches it while it is open.
	let dialog: { contentLocale: string } | null = $state(null);

	$effect(() => {
		if (dialog) {
			dialog.contentLocale = contentLocale;
		}
	});

	function createSorter() {
		if (sorterElement) {
			Sortable.create(sorterElement, {
				animation: 200,
				onUpdate: function (event: SortableEvent) {
					if (event.oldIndex === undefined || event.newIndex === undefined) {
						return;
					}

					const tmp = items[event.oldIndex];

					items.splice(event.oldIndex, 1);
					items.splice(event.newIndex, 0, tmp);
					items = items;
					// The element only serializes into the form value when
					// notified; without this the reorder is lost on save.
					notify();
				},
			});
		}
	}

	function edit(index: number, kind: 'video' | 'file') {
		const props = $state({
			asset: items[index],
			kind,
			close: () => handle.close(),
			apply: (item: FileItem) => {
				handle.close();
				// Empty per-use meta is dropped so catalog defaults apply.
				items[index] = pruneItemMeta(item);
				notify();
			},
			translate,
			contentLocale,
			locales,
		});
		const handle = cosray().modal.open(
			(host) => {
				dialog = props;
				const app = mount(ModalEditImage, {
					target: host,
					props,
					// A separate mount: the catalog fallbacks need the element's store.
					context: assetsContext(assets),
				});

				return () => {
					dialog = null;
					void unmount(app);
				};
			},
			{ owner: sorterElement ?? document.getElementById(identity) ?? undefined },
		);
	}

	onMount(() => {
		if (!readonly) {
			createSorter();
		}
	});
</script>

<div class="cms-media-list" bind:this={sorterElement}>
	{#if multiple && type === 'file'}
		{#each items as item, index (item)}
			<File
				{loading}
				{readonly}
				asset={item}
				remove={() => remove(index)}
				edit={() => edit(index, 'file')}
			/>
		{/each}
	{:else if !multiple && type === 'video' && items && items.length > 0}
		<Video
			upload
			file={items[0]}
			remove={() => remove(null)}
			edit={() => edit(0, 'video')}
			{loading}
			{readonly}
		/>
	{:else if items && items.length > 0}
		<File
			{loading}
			{readonly}
			asset={items[0]}
			remove={() => remove(null)}
			edit={() => edit(0, 'file')}
		/>
	{/if}
</div>

<style>
	@layer panel {
		.cms-media-list {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-3);
			padding: var(--cms-space-3);
		}
	}
</style>
