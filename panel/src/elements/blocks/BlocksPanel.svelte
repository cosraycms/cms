<script lang="ts">
	import type { Block } from '$types/data';
	import type { BlocksField, BlockTypeMeta } from '$types/fields';

	import { _ } from '$lib/locale';
	import resize from '$lib/resize';
	import type { Component } from 'svelte';
	import { flip } from 'svelte/animate';
	import { useNotify } from '../notify';
	import IcoCirclePlus from '$shell/icons/IcoCirclePlus.svelte';
	import Button from '$shell/Button.svelte';
	import ModalAdd from '$shell/modals/ModalAdd.svelte';
	import BlocksControls from './BlocksControls.svelte';
	import BlockElement from './BlockElement.svelte';
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
	const notify = useNotify();
	import { mount, unmount } from 'svelte';
	import { cosray } from '$lib/bridge';

	// Block controls dispatch on the control NAME from the server-side
	// block type descriptor, never on the block type id itself.
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	const controls: Record<string, Component<any>> = {
		'block-text': BlockText,
		'block-richtext': BlockRichText,
		'block-image': BlockImage,
		'block-images': BlockImages,
		'block-youtube': BlockYoutube,
		'block-video': BlockVideo,
		'block-iframe': BlockIframe,
		element: BlockElement,
	};

	let blockTypes = $derived(field.blockTypes ?? []);
	let types = $derived(
		blockTypes.filter((type) => !type.hidden).map(({ id, label }) => ({ id, label })),
	);

	function blockMeta(id: string): BlockTypeMeta | undefined {
		return blockTypes.find((type) => type.id === id);
	}

	function blockControl(id: string): Component<any> | undefined {
		const name = blockMeta(id)?.control.name;

		return name ? controls[name] : undefined;
	}

	function add(index: number | null, before: boolean, type: string) {
		const meta = blockMeta(type);

		if (!meta) {
			return;
		}

		const content = structuredClone(meta.init) as unknown as Block;

		if (!data) {
			data = [];
		}

		if (index === null) {
			data.push(content);
		} else {
			if (before) {
				data.splice(index, 0, content);
			} else {
				if (data.length - 1 === index) {
					data.push(content);
				} else {
					data.splice(index + 1, 0, content);
				}
			}
		}

		notify();
	}

	function openAddModal(index: number | null) {
		return () => {
			const handle = cosray().modal.open((host) => {
				const app = mount(ModalAdd, {
					target: host,
					props: {
						index,
						add,
						close: () => handle.close(),
						types,
					},
				});

				return () => void unmount(app);
			});
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

<div class="blocks-field cms-blocks-field" style={blocksStyle(cols)}>
	{#if data && data.length > 0}
		{#each data as item, index (item)}
			{@const Control = blockControl(item.type)}
			<div
				class="cms-block"
				style={blockStyle(item)}
				animate:flip={{ duration: 300 }}
				use:resize={resizeCell(item)}
			>
				{#if !Control}
					<div class="cms-control-unknown">Unknown block type "{item.type}"</div>
				{:else}
					<Control {item} {node} {index} {field}>
						{#snippet children(params: { edit: () => void })}
							<BlocksControls
								bind:data
								{item}
								{index}
								{field}
								edit={params.edit}
								add={openAddModal(index)}
							/>
						{/snippet}
					</Control>
				{/if}
			</div>
		{/each}
	{:else}
		<div class="cms-blocks-empty">
			<Button variant="secondary" onclick={openAddModal(null)}>
				<span class="cms-blocks-empty-icon">
					<IcoCirclePlus />
				</span>
				{_('Inhalt hinzfügen')}
			</Button>
		</div>
	{/if}
</div>

<style>
	@layer panel {
		.cms-blocks-field {
			display: grid;
			gap: var(--space-3);
			padding: var(--space-3);
			border-radius: var(--radius);
			border: var(--border);
			background-color: var(--color-neutral-200);
		}

		.cms-block {
			position: relative;
			display: flex;
			flex-direction: column;
			border-radius: var(--radius);
			border: var(--border);
			background-color: var(--color-white);
			padding: 0 var(--space-2) var(--space-2);
		}

		.cms-blocks-empty {
			grid-column: 1 / -1;
			display: flex;
			justify-content: center;
			padding: var(--space-4);
		}

		.cms-blocks-empty-icon {
			display: inline-flex;
			width: 1.25rem;
			height: 1.25rem;
			margin-right: var(--space-2);
		}

		.cms-control-unknown {
			padding: 0.75rem;
			color: var(--color-danger);
			font-size: 0.875rem;
		}
	}
</style>
