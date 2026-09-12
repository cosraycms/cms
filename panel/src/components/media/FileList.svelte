<script lang="ts">
	import type { FileItem } from '$types/data';
	import type { SortableEvent } from 'sortablejs';

	import Sortable from 'sortablejs';
	import { mount, unmount } from 'svelte';
	import { assetsContext, useAssets } from '$lib/assets';
	import { cosray } from '$lib/bridge';
	import { pruneItemMeta } from '$lib/content';
	import ModalEditImage from '$components/modals/ModalEditImage.svelte';
	import FileRow from './FileRow.svelte';

	type Props = {
		items: FileItem[];
		// A file's pencil edits its title, a video's its caption.
		kind: 'file' | 'video';
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		loading: boolean;
		remove: (index: number) => void;
		/** The field cannot change: no reordering, no per-item actions. */
		readonly?: boolean;
		notify: () => void;
	};

	let {
		items = $bindable(),
		kind,
		translate,
		contentLocale,
		identity,
		locales,
		loading,
		remove,
		readonly = false,
		notify,
	}: Props = $props();

	const assets = useAssets();
	let list: HTMLElement | undefined = $state();
	// The open dialog's props stay live, so a language chosen through its
	// mirrored selector reaches it while it is open.
	let dialog: { contentLocale: string } | null = $state(null);

	$effect(() => {
		if (dialog) {
			dialog.contentLocale = contentLocale;
		}
	});

	$effect(() => {
		if (!list || readonly) {
			return;
		}

		const sorter = Sortable.create(list, {
			animation: 200,
			onUpdate(event: SortableEvent) {
				if (event.oldIndex === undefined || event.newIndex === undefined) {
					return;
				}

				const [moved] = items.splice(event.oldIndex, 1);

				items.splice(event.newIndex, 0, moved);
				items = items;
				// The element only serializes into the form value when
				// notified; without this the reorder is lost on save.
				notify();
			},
		});

		return () => sorter.destroy();
	});

	function edit(index: number) {
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
			{ owner: list ?? document.getElementById(identity) ?? undefined },
		);
	}
</script>

<div class="cms-file-list" bind:this={list}>
	{#each items as item, index (item)}
		<FileRow
			{item}
			{translate}
			{contentLocale}
			{loading}
			inert={readonly}
			edit={() => edit(index)}
			remove={() => remove(index)}
		/>
	{/each}
</div>

<style>
	@layer panel {
		.cms-file-list {
			display: flex;
			flex-direction: column;
		}
	}
</style>
