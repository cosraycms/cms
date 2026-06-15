<script lang="ts">
	import type {
		Block,
		BlockBase,
		BlockText as BlockTextData,
		BlockImage as BlockImageData,
		BlockYoutube as BlockYoutubeData,
		BlockType,
	} from '$types/data';
	import type { BlocksField } from '$types/fields';
	import type { ModalFunctions } from '$shell/modal';

	import { _ } from '$lib/locale';
	import resize from '$lib/resize';
	import { getContext } from 'svelte';
	import type { Component } from 'svelte';
	import { flip } from 'svelte/animate';
	import { setDirty } from '$lib/state';
	import IcoCirclePlus from '$shell/icons/IcoCirclePlus.svelte';
	import Button from '$shell/Button.svelte';
	import ModalAdd from '$shell/modals/ModalAdd.svelte';
	import BlocksControls from './BlocksControls.svelte';
	import BlockImage from './BlockImage.svelte';
	import BlockImages from './BlockImages.svelte';
	import BlockRichText from './BlockRichText.svelte';
	import BlockText from './BlockText.svelte';
	import BlockYoutube from './BlockYoutube.svelte';
	import BlockIframe from './BlockIframe.svelte';
	import BlockVideo from './BlockVideo.svelte';

	type Props = {
		field: BlocksField;
		data: Block[];
		node: string;
		cols?: number;
	};

	let { field, data = $bindable(), node, cols = 12 }: Props = $props();
	let { open, close } = getContext<ModalFunctions>('modal');

	const controls: Record<BlockType, Component<any>> = {
		image: BlockImage,
		richtext: BlockRichText,
		text: BlockText,
		h1: BlockText,
		h2: BlockText,
		h3: BlockText,
		h4: BlockText,
		h5: BlockText,
		h6: BlockText,
		youtube: BlockYoutube,
		images: BlockImages,
		video: BlockVideo,
		iframe: BlockIframe,
	};
	const types = [
		{ id: 'richtext', label: 'Formatierter Text' },
		{ id: 'text', label: 'Einfacher Text' },
		{ id: 'image', label: 'Einzelbild' },
		{ id: 'youtube', label: 'Youtube-Video' },
		{ id: 'images', label: 'Mehrere Bilder' },
		{ id: 'video', label: 'Video' },
		{ id: 'iframe', label: 'Iframe' },
	];

	function add(index: number | null, before: boolean, type: BlockType) {
		let content: BlockBase = {
			type,
			colspan: 12,
			rowspan: 1,
			colstart: null,
		};
		if (['richtext', 'text', 'iframe', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'].includes(type)) {
			(content as BlockTextData).value = { zxx: '' };
		} else if (type === 'image' || type === 'images' || type === 'video') {
			(content as BlockImageData).value = [];
		} else if (type === 'youtube') {
			(content as BlockYoutubeData).value = { zxx: '' };
			(content as BlockYoutubeData).meta = {
				aspectRatioX: { zxx: 16 },
				aspectRatioY: { zxx: 9 },
			};
		}

		if (!data) {
			data = [];
		}

		if (index === null) {
			data.push(content as Block);
		} else {
			if (before) {
				data.splice(index, 0, content as Block);
			} else {
				if (data.length - 1 === index) {
					data.push(content as Block);
				} else {
					data.splice(index + 1, 0, content as Block);
				}
			}
		}

		setDirty();
	}

	function openAddModal(index: number | null) {
		return () => {
			open(
				ModalAdd as Component<any>,
				{
					index,
					add: (index: number | null, before: boolean, type: string) =>
						add(index, before, type as BlockType),
					close,
					types,
				},
				{},
			);
		};
	}

	function resizeCell(item: Block) {
		return (element: HTMLElement) => (item.width = element.clientWidth);
	}

	function blocksStyle(columns: number): string {
		return `grid-template-columns: repeat(${columns}, minmax(0, 1fr));`;
	}

	function blockStyle(item: Block): string {
		const column = item.colstart
			? `${item.colstart} / span ${item.colspan}`
			: `span ${item.colspan} / span ${item.colspan}`;

		return `grid-column: ${column}; grid-row: span ${item.rowspan} / span ${item.rowspan};`;
	}
</script>

<div
	class="blocks-field cms-blocks-field"
	style={blocksStyle(cols)}>
	{#if data && data.length > 0}
		{#each data as item, index (item)}
			{@const Control = controls[item.type]}
			<div
				class="cms-block"
				style={blockStyle(item)}
				animate:flip={{ duration: 300 }}
				use:resize={resizeCell(item)}>
				<Control
					{item}
					{node}
					{index}
					{field}>
					{#snippet children(params: { edit: () => void })}
						<BlocksControls
							bind:data
							{item}
							{index}
							{field}
							edit={params.edit}
							add={openAddModal(index)} />
					{/snippet}
				</Control>
			</div>
		{/each}
	{:else}
		<div class="cms-blocks-empty">
			<Button
				class="secondary"
				onclick={openAddModal(null)}>
				<span class="cms-blocks-empty-icon">
					<IcoCirclePlus />
				</span>
				{_('Inhalt hinzfügen')}
			</Button>
		</div>
	{/if}
</div>

<style lang="postcss">
	.cms-blocks-field {
		display: grid;
		gap: var(--cms-space-3);
		padding: var(--cms-space-3);
		border-radius: var(--cms-radius);
		border: var(--cms-border);
		background-color: var(--cms-color-neutral-200);
	}

	.cms-block {
		position: relative;
		display: flex;
		flex-direction: column;
		border-radius: var(--cms-radius);
		border: var(--cms-border);
		background-color: var(--cms-color-white);
		padding: 0 var(--cms-space-2) var(--cms-space-2);
	}

	.cms-blocks-empty {
		grid-column: 1 / -1;
		display: flex;
		justify-content: center;
		padding: var(--cms-space-4);
	}

	.cms-blocks-empty-icon {
		display: inline-flex;
		width: 1.25rem;
		height: 1.25rem;
		margin-right: var(--cms-space-2);
	}
</style>
