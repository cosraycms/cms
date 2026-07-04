<script lang="ts">
	import type { FileItem, UploadType } from '$types/data';
	import type { SortableEvent } from 'sortablejs';
	import Sortable from 'sortablejs';
	import { mount, onMount, unmount } from 'svelte';
	import { cosray } from '$lib/bridge';
	import { pruneItemMeta } from '$lib/content';
	import Image from '$shell/Image.svelte';
	import Video from '$shell/Video.svelte';
	import File from '$shell/File.svelte';
	import ModalEditImage from '$shell/modals/ModalEditImage.svelte';

	type Props = {
		items: FileItem[];
		multiple: boolean;
		translate: boolean;
		type: UploadType;
		loading: boolean;
		remove: (index: number | null) => void;
		notify?: () => void;
	};

	let {
		items = $bindable(),
		multiple,
		translate,
		type,
		loading,
		remove,
		notify = () => {},
	}: Props = $props();
	let sorterElement: HTMLElement | undefined = $state();

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

	function edit(index: number, hasAlt: boolean) {
		const handle = cosray().modal.open((host) => {
			const app = mount(ModalEditImage, {
				target: host,
				props: {
					asset: items[index],
					close: () => handle.close(),
					apply: (item: FileItem) => {
						// Empty per-use meta is dropped so catalog defaults apply.
						items[index] = pruneItemMeta(item);
						notify();
						handle.close();
					},
					translate,
					hasAlt,
				},
			});

			return () => void unmount(app);
		});
	}

	onMount(createSorter);
</script>

{#if multiple && type === 'image'}
	<div class="multiple-images cms-media-list cms-media-list-images" bind:this={sorterElement}>
		{#each items as item, index (item)}
			<Image
				upload
				{multiple}
				image={item}
				remove={() => remove(index)}
				edit={() => edit(index, true)}
				{loading}
			/>
		{/each}
	</div>
{:else if !multiple && type === 'image' && items && items.length > 0}
	<Image
		upload
		{multiple}
		image={items[0]}
		remove={() => remove(null)}
		edit={() => edit(0, true)}
		{loading}
	/>
{:else if multiple && type === 'file'}
	<div class="multiple-files cms-media-list cms-media-list-files" bind:this={sorterElement}>
		{#each items as item, index (item)}
			<File {loading} asset={item} remove={() => remove(index)} edit={() => edit(index, false)} />
		{/each}
	</div>
{:else if !multiple && type === 'video' && items && items.length > 0}
	<Video upload file={items[0]} remove={() => remove(null)} {loading} />
{:else if items && items.length > 0}
	<File {loading} asset={items[0]} remove={() => remove(null)} edit={() => edit(0, false)} />
{/if}

<style>
	@layer panel {
		.cms-media-list {
			display: flex;
		}

		.cms-media-list-images {
			flex-direction: row;
			flex-wrap: wrap;
			justify-content: flex-start;
			gap: var(--space-4);
			padding: var(--space-4) 0;
		}

		.cms-media-list-files {
			margin-bottom: var(--space-3);
			flex-direction: column;
			gap: var(--space-3);
		}
	}
</style>
