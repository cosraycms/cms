<script lang="ts">
	import type { FileItem } from '$types/data';
	import type { SortableEvent } from 'sortablejs';

	import Sortable from 'sortablejs';
	import { onMount } from 'svelte';
	import { useAssets } from '$lib/assets';
	import { afterMove, afterRemove } from '$lib/gallery';
	import { assetLine, extension } from '$lib/library';
	import { __ } from '$lib/locale';
	import IcoChevronLeft from '$components/icons/IcoChevronLeft.svelte';
	import IcoChevronRight from '$components/icons/IcoChevronRight.svelte';
	import IcoPlus from '$components/icons/IcoPlus.svelte';
	import IcoTimes from '$components/icons/IcoTimes.svelte';
	import IcoUpload from '$components/icons/IcoUpload.svelte';
	import MetaFields from './MetaFields.svelte';

	type Props = {
		items: FileItem[];
		loading: boolean;
		translate: boolean;
		// False once the field's limit is reached; hides the add actions.
		open: boolean;
		remove: (index: number) => void;
		upload: () => void;
		library: () => void;
		notify: () => void;
	};

	let {
		items = $bindable(),
		loading,
		translate,
		open,
		remove,
		upload,
		library,
		notify,
	}: Props = $props();

	const assets = useAssets();

	let selected: number | null = $state(null);
	let grid: HTMLElement | undefined = $state();
	let current = $derived(selected === null ? null : (items[selected] ?? null));
	let currentInfo = $derived(current?.uid ? $assets[current.uid] : undefined);
	let count = $derived(
		items.length === 1
			? __('image:count-one', { count: 1 })
			: __('image:count-many', { count: items.length }),
	);

	function filename(item: FileItem): string {
		return $assets[item.uid ?? '']?.filename ?? item.uid ?? '';
	}

	function thumb(item: FileItem): string {
		const info = $assets[item.uid ?? ''];

		return info?.thumbUrl ?? info?.url ?? '';
	}

	function select(index: number) {
		selected = selected === index ? null : index;
	}

	function step(delta: number) {
		if (selected !== null && items.length > 0) {
			selected = (selected + delta + items.length) % items.length;
		}
	}

	function removeAt(index: number) {
		remove(index);
		selected = afterRemove(selected, index, items.length);
	}

	function update(item: FileItem) {
		if (selected !== null) {
			items[selected] = item;
			notify();
		}
	}

	onMount(() => {
		if (!grid) {
			return;
		}

		Sortable.create(grid, {
			animation: 200,
			onUpdate(event: SortableEvent) {
				if (event.oldIndex === undefined || event.newIndex === undefined) {
					return;
				}

				const [moved] = items.splice(event.oldIndex, 1);

				items.splice(event.newIndex, 0, moved);
				items = items;
				selected = afterMove(selected, event.oldIndex, event.newIndex);
				// The element only serializes into the form value when
				// notified; without this the reorder is lost on save.
				notify();
			},
		});
	});
</script>

<div class="cms-gallery">
	<div class="summary">
		<span class="tally">{loading ? __('upload:uploading') : count}</span>
		{#if open}
			<span class="tools">
				<button type="button" class="textlink" onclick={library}>
					{__('media:choose-from-library')}
				</button>
				<button type="button" class="cms-button secondary small" onclick={upload}>
					<span class="icon"><IcoPlus /></span>
					{__('image:add')}
				</button>
			</span>
		{/if}
	</div>
	{#if items.length > 0}
		<div class="viewport">
			<div class="tiles" bind:this={grid}>
				{#each items as item, index (item)}
					<div class="tile" class:is-selected={selected === index} title={filename(item)}>
						<button type="button" class="pick" onclick={() => select(index)}>
							{#if thumb(item)}
								<img src={thumb(item)} alt="" loading="lazy" />
							{:else}
								<span class="plate">{extension(filename(item))}</span>
							{/if}
						</button>
						<button
							type="button"
							class="discard"
							title={__('common:remove')}
							onclick={() => removeAt(index)}
						>
							<IcoTimes />
						</button>
					</div>
				{/each}
			</div>
		</div>
	{:else}
		<div class="blank">
			<IcoUpload />
			<span>{__('upload:drop-images')}</span>
		</div>
	{/if}
	{#if current && selected !== null}
		<div class="drawer">
			<div class="drawer-head">
				{#if thumb(current)}
					<img class="mini" src={thumb(current)} alt="" />
				{:else}
					<span class="mini"></span>
				{/if}
				<span class="filename" title={filename(current)}>{filename(current)}</span>
				<span class="stepper">
					<span class="position">{selected + 1} / {items.length}</span>
					<button
						type="button"
						class="step prev"
						title={__('image:previous')}
						onclick={() => step(-1)}
					>
						<IcoChevronLeft />
					</button>
					<button type="button" class="step next" title={__('image:next')} onclick={() => step(1)}>
						<IcoChevronRight />
					</button>
					<button
						type="button"
						class="dismiss"
						title={__('common:close')}
						onclick={() => (selected = null)}
					>
						<IcoTimes />
					</button>
				</span>
			</div>
			{#if currentInfo && assetLine(currentInfo) !== ''}
				<div class="facts">{assetLine(currentInfo)}</div>
			{/if}
			{#key current.uid}
				<MetaFields item={current} {translate} {update} />
			{/key}
		</div>
	{/if}
</div>

<style>
	@layer panel {
		.cms-gallery {
			display: flex;
			flex-direction: column;
			min-height: 0;

			& .summary {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: var(--cms-space-2);
				padding: var(--cms-space-2) var(--cms-space-3) var(--cms-space-2) var(--cms-space-3-5);
				border-bottom: 1px solid var(--cms-color-border);
			}

			& .tally {
				font-size: var(--cms-font-size-xs);
				font-weight: 500;
				color: var(--cms-color-text-muted);
				font-variant-numeric: tabular-nums;
			}

			& .summary > .tools {
				display: flex;
				align-items: center;
				gap: var(--cms-space-2);
				margin-left: auto;
			}

			& .textlink {
				padding: var(--cms-space-1) var(--cms-space-1);
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

			& .icon :global(svg) {
				width: 0.8125rem;
				height: 0.8125rem;
			}

			& .viewport {
				max-height: 17.5rem;
				padding: var(--cms-space-3-5);
				overflow-y: auto;
				overscroll-behavior: contain;
			}

			& .tiles {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(8rem, 1fr));
				gap: var(--cms-space-3);
			}

			/* Square, bordered, the image contained inside a small inset: every
			   tile keeps one silhouette, and badges or cut-outs are never cropped. */
			& .tile {
				position: relative;
				aspect-ratio: 1;
				padding: var(--cms-space-1);
				border: 1px solid var(--cms-color-border);
				border-radius: var(--cms-radius-md);
				background: var(--cms-color-surface);

				&.is-selected {
					border-color: var(--cms-color-info);
					box-shadow: 0 0 0 2px var(--cms-color-info-ring);
				}

				&:hover .discard,
				&:focus-within .discard,
				&.is-selected .discard {
					opacity: 1;
				}
			}

			& .pick {
				display: flex;
				align-items: center;
				justify-content: center;
				width: 100%;
				height: 100%;
				padding: 0;
				border: 0;
				background: transparent;
				cursor: pointer;

				& img {
					max-width: 100%;
					max-height: 100%;
					border-radius: var(--cms-radius);
				}
			}

			& .plate {
				font-size: 0.625rem;
				letter-spacing: 0.08em;
				text-transform: uppercase;
				color: var(--cms-color-text-subtle);
			}

			& .discard {
				position: absolute;
				top: var(--cms-space-1);
				right: var(--cms-space-1);
				display: grid;
				place-items: center;
				width: 1.25rem;
				height: 1.25rem;
				padding: 0;
				border: 0;
				border-radius: var(--cms-radius);
				background: color-mix(in srgb, var(--cms-color-surface) 90%, transparent);
				box-shadow: var(--cms-shadow-sm);
				color: var(--cms-color-text-muted);
				opacity: 0;
				transition: opacity 0.12s ease;
				cursor: pointer;

				&:hover {
					background: var(--cms-color-surface);
					color: var(--cms-color-text);
				}

				& :global(svg) {
					width: 0.75rem;
					height: 0.75rem;
				}
			}

			& .blank {
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: var(--cms-space-2);
				padding: var(--cms-space-8) var(--cms-space-4);
				font-size: var(--cms-font-size-sm);
				font-weight: 500;
				color: var(--cms-color-text-subtle);

				& :global(svg) {
					width: var(--cms-space-5);
					height: var(--cms-space-5);
					color: var(--cms-color-text-faint);
				}
			}

			& .drawer {
				display: flex;
				flex-direction: column;
				gap: var(--cms-space-3);
				padding: var(--cms-space-3-5);
				border-top: 1px solid var(--cms-color-border);
				border-radius: 0 0 var(--cms-radius-md) var(--cms-radius-md);
				background: var(--cms-color-surface-sunken);
			}

			& .drawer-head {
				display: flex;
				align-items: center;
				gap: var(--cms-space-2-5);
			}

			& .mini {
				width: 2rem;
				height: 2rem;
				flex-shrink: 0;
				padding: var(--cms-space-0-5);
				border: 1px solid var(--cms-color-border);
				border-radius: var(--cms-radius);
				background: var(--cms-color-surface);
				object-fit: contain;
			}

			& .filename {
				flex: 1 1 auto;
				min-width: 0;
				font-size: var(--cms-font-size-xs);
				font-weight: 600;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			& .stepper {
				display: flex;
				flex-shrink: 0;
				align-items: center;
				margin-left: auto;
			}

			& .position {
				padding-right: var(--cms-space-1-5);
				font-size: var(--cms-font-size-xs);
				color: var(--cms-color-text-faint);
				font-variant-numeric: tabular-nums;
			}

			& .step,
			& .dismiss {
				display: grid;
				place-items: center;
				width: 1.625rem;
				height: 1.625rem;
				padding: 0;
				color: var(--cms-color-text-muted);
				cursor: pointer;

				& :global(svg) {
					width: 0.75rem;
					height: 0.75rem;
				}

				&:hover {
					background: var(--cms-color-hover);
					color: var(--cms-color-text);
				}
			}

			& .step {
				border: 1px solid var(--cms-color-border-strong);
				background: var(--cms-color-surface);

				&.prev {
					border-right: 0;
					border-radius: var(--cms-radius-md) 0 0 var(--cms-radius-md);
				}

				&.next {
					border-radius: 0 var(--cms-radius-md) var(--cms-radius-md) 0;
				}
			}

			& .dismiss {
				margin-left: var(--cms-space-1-5);
				border: 0;
				border-radius: var(--cms-radius-md);
				background: transparent;
			}

			& .facts {
				font-size: var(--cms-font-size-xs);
				color: var(--cms-color-text-faint);
				font-variant-numeric: tabular-nums;
			}
		}
	}
</style>
