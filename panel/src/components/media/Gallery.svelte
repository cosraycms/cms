<script lang="ts">
	import type { FileItem, Meta } from '$types/data';
	import type { SortableEvent } from 'sortablejs';

	import Sortable from 'sortablejs';
	import { ZXX } from '$types/data';
	import { useAssets } from '$lib/assets';
	import { RATIOS, afterMove, afterRemove, readRatio } from '$lib/gallery';
	import { assetLine, extension } from '$lib/library';
	import { __ } from '$lib/locale';
	import { portal } from '$lib/portal';
	import Icon from '$components/Icon.svelte';
	import MetaFields from './MetaFields.svelte';

	type Props = {
		items: FileItem[];
		loading: boolean;
		translate: boolean;
		contentLocale: string;
		identity: string;
		locales?: { default: string; all: { id: string; title: string; fallback?: string | null }[] };
		// False once the field's limit is reached; hides the add actions.
		open: boolean;
		// The block presentation shows the tiles alone; the per-image drawer
		// and the gallery settings go into the settings slot.
		presentation?: string;
		settings?: HTMLElement;
		// The gallery settings — ratio and crop — kept as the field's meta.
		meta?: Meta;
		updateMeta?: (meta: Meta) => void;
		remove: (index: number) => void;
		upload: () => void;
		library: () => void;
		notify: () => void;
	};

	let {
		items = $bindable(),
		loading,
		translate,
		contentLocale,
		identity,
		locales,
		open,
		presentation,
		settings,
		meta,
		updateMeta,
		remove,
		upload,
		library,
		notify,
	}: Props = $props();

	const assets = useAssets();
	const id = $props.id();

	let block = $derived(presentation === 'block');
	let selected: number | null = $state(null);
	let grid: HTMLElement | undefined = $state();
	// The dialog's drawer always shows an image; the inline drawer opens on a pick.
	let current = $derived(selected ?? (block && items.length > 0 ? 0 : null));
	let currentItem = $derived(current === null ? null : (items[current] ?? null));
	let currentInfo = $derived(currentItem?.uid ? $assets[currentItem.uid] : undefined);
	let ratio = $derived(readRatio(meta?.ratio?.[ZXX]));
	let crop = $derived(meta?.crop?.[ZXX] === true);
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
		selected = block || selected !== index ? index : null;
	}

	function step(delta: number) {
		if (current !== null && items.length > 0) {
			selected = (current + delta + items.length) % items.length;
		}
	}

	function removeAt(index: number) {
		remove(index);
		selected = afterRemove(selected, index, items.length);
	}

	function update(item: FileItem) {
		if (current !== null) {
			items[current] = item;
			notify();
		}
	}

	// Auto and crop-off are the site's defaults and are not stored.
	function setSettings(nextRatio: string, nextCrop: boolean) {
		const next: Meta = { ...meta };

		delete next.ratio;
		delete next.crop;

		if (nextRatio !== 'auto') {
			next.ratio = { [ZXX]: nextRatio };
		}

		if (nextCrop) {
			next.crop = { [ZXX]: true };
		}

		updateMeta?.(next);
	}

	$effect(() => {
		if (!grid) {
			return;
		}

		const sorter = Sortable.create(grid, {
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

		return () => sorter.destroy();
	});
</script>

{#snippet editor(index: number, item: FileItem)}
	<div class="drawer-head">
		{#if !block}
			{#if thumb(item)}
				<img class="mini" src={thumb(item)} alt="" />
			{:else}
				<span class="mini"></span>
			{/if}
		{/if}
		<span class="filename" title={filename(item)}>{filename(item)}</span>
		<span class="stepper">
			<span class="position">{index + 1} / {items.length}</span>
			<button
				type="button"
				class="step prev"
				title={__('image:previous')}
				aria-label={__('image:previous')}
				onclick={() => step(-1)}
			>
				<Icon name="chevron-left" />
			</button>
			<button
				type="button"
				class="step next"
				title={__('image:next')}
				aria-label={__('image:next')}
				onclick={() => step(1)}
			>
				<Icon name="chevron-right" />
			</button>
			{#if !block}
				<button
					type="button"
					class="dismiss"
					title={__('common:close')}
					aria-label={__('common:close')}
					onclick={() => (selected = null)}
				>
					<Icon name="x-lg" />
				</button>
			{/if}
		</span>
	</div>
	{#if currentInfo && assetLine(currentInfo) !== ''}
		<div class="facts">{assetLine(currentInfo)}</div>
	{/if}
	{#key `${identity}:${item.uid}`}
		<MetaFields {item} kind="image" {translate} {contentLocale} {locales} {update} />
	{/key}
{/snippet}

<div class="cms-gallery" class:is-block={block}>
	{#if !block}
		<div class="summary">
			<span class="tally">{loading ? __('upload:uploading') : count}</span>
			{#if open}
				<span class="tools">
					<button type="button" class="textlink" onclick={library}>
						{__('media:choose-from-library')}
					</button>
					<button type="button" class="cms-button secondary small" onclick={upload}>
						<span class="icon"><Icon name="plus" /></span>
						{__('image:add')}
					</button>
				</span>
			{/if}
		</div>
	{/if}
	{#if items.length > 0}
		<div class="viewport">
			<div
				class="tiles"
				class:has-ratio={ratio !== 'auto'}
				class:is-cropped={crop}
				style:--ratio={ratio !== 'auto' ? ratio : null}
				bind:this={grid}
			>
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
							aria-label={__('common:remove')}
							onclick={() => removeAt(index)}
						>
							<Icon name="x-lg" />
						</button>
					</div>
				{/each}
			</div>
		</div>
		{#if block && open}
			<div class="bar">
				<span class="status">{loading ? __('upload:uploading') : count}</span>
				<button type="button" class="quiet" onclick={library}>
					{__('media:choose-from-library')}
				</button>
				<button type="button" class="quiet" onclick={upload}>{__('image:add')}</button>
			</div>
		{/if}
	{:else if block}
		<div class="dropzone">
			<Icon name="cloud-upload" />
			<span class="prompt">{loading ? __('upload:uploading') : __('upload:drop-images-here')}</span>
			<span class="tools">
				<button type="button" class="cms-button secondary small" onclick={upload}>
					{__('image:add')}
				</button>
				<button type="button" class="textlink" onclick={library}>
					{__('media:choose-from-library')}
				</button>
			</span>
		</div>
	{:else}
		<div class="blank">
			<Icon name="cloud-upload" />
			<span>{__('upload:drop-images')}</span>
		</div>
	{/if}
	{#if !block && currentItem && current !== null}
		<div class="drawer">
			{@render editor(current, currentItem)}
		</div>
	{/if}
</div>
{#if block}
	<div class="cms-gallery-settings" use:portal={settings}>
		<div class="options">
			<label class="option" for="{id}-ratio">
				<span>{__('image:ratio')}</span>
				<select
					class="cms-select"
					id="{id}-ratio"
					value={ratio}
					onchange={(event) => setSettings(event.currentTarget.value, crop)}
				>
					{#each RATIOS as value (value)}
						<option {value}>{value === 'auto' ? __('image:ratio-auto') : value}</option>
					{/each}
				</select>
			</label>
			<label class="check">
				<input
					type="checkbox"
					checked={crop}
					onchange={(event) => setSettings(ratio, event.currentTarget.checked)}
				/>
				<span>{__('image:crop')}</span>
			</label>
		</div>
		{#if currentItem && current !== null}
			<div class="strip">
				{#each items as item, index (item)}
					<button
						type="button"
						class="thumb"
						class:is-current={current === index}
						title={filename(item)}
						onclick={() => (selected = index)}
					>
						{#if thumb(item)}
							<img src={thumb(item)} alt="" loading="lazy" />
						{:else}
							<span class="plate">{extension(filename(item))}</span>
						{/if}
					</button>
				{/each}
			</div>
			{@render editor(current, currentItem)}
		{/if}
	</div>
{/if}

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

			& .tile {
				position: relative;
				aspect-ratio: 1;
				padding: var(--cms-space-1);
				border: 1px solid var(--cms-color-border);
				border-radius: var(--cms-radius-md);
				background: var(--cms-color-surface);

				&.is-selected {
					border-color: var(--cms-color-accent);
					box-shadow: 0 0 0 2px var(--cms-color-accent-ring);
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

			/*
			 * The block presentation: the tiles at block width and nothing
			 * else at rest. Add and library sit on a bar along the bottom
			 * edge that appears on hover; the tiles preview the ratio and
			 * crop chosen in the settings dialog.
			 */
			&.is-block {
				position: relative;

				& .viewport {
					max-height: none;
					padding: 0;
					overflow: visible;
				}

				& .tiles {
					grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
					gap: var(--cms-space-2);
				}

				& .tile {
					aspect-ratio: auto;
					padding: 0;
					border: 0;
					border-radius: var(--cms-radius-sm);
					background: none;
					overflow: hidden;

					&.is-selected {
						box-shadow: 0 0 0 2px var(--cms-color-accent-ring);
					}
				}

				& .tiles.has-ratio .tile {
					aspect-ratio: var(--ratio);
				}

				& .pick {
					display: block;
					height: auto;

					& img {
						display: block;
						width: 100%;
						height: auto;
						max-height: none;
						border-radius: 0;
					}
				}

				& .tiles.has-ratio .pick {
					height: 100%;

					& img {
						height: 100%;
						object-fit: contain;
					}
				}

				& .tiles.is-cropped .pick img {
					object-fit: cover;
				}

				& .plate {
					display: grid;
					place-items: center;
					aspect-ratio: 4 / 3;
					background: var(--cms-color-surface-sunken);
				}

				& .bar {
					position: absolute;
					inset-inline: 0;
					inset-block-end: 0;
					display: flex;
					align-items: center;
					gap: var(--cms-space-0-5);
					padding: var(--cms-space-1-5) var(--cms-space-2);
					border-radius: 0 0 var(--cms-radius-sm) var(--cms-radius-sm);
					background: color-mix(in srgb, var(--cms-color-surface) 88%, transparent);
					opacity: 0;
					pointer-events: none;
					transition: opacity 120ms ease;
				}

				&:hover .bar,
				&:focus-within .bar {
					opacity: 1;
					pointer-events: auto;
				}

				& .status {
					flex: 1 1 auto;
					font-size: var(--cms-font-size-xs);
					color: var(--cms-color-text-muted);
					font-variant-numeric: tabular-nums;
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

				& .dropzone .tools {
					display: flex;
					align-items: center;
					gap: var(--cms-space-2);
					margin-top: var(--cms-space-1);
				}
			}
		}

		/* The drawer in the settings dialog: a strip of thumbs to pick from,
		   the gallery settings above it. */
		.cms-gallery-settings {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-3);

			& .options {
				display: flex;
				flex-direction: column;
				gap: var(--cms-space-2);
			}

			& .option {
				display: flex;
				flex-direction: column;
				gap: var(--cms-space-1);
				color: var(--cms-color-text-label);
				font-size: var(--cms-font-size-sm);
				font-weight: 500;
			}

			& .check {
				display: flex;
				align-items: center;
				gap: var(--cms-space-2);
				font-size: var(--cms-font-size-sm);
			}

			& .strip {
				display: flex;
				gap: var(--cms-space-1-5);
				padding-block: var(--cms-space-1);
				overflow-x: auto;
			}

			& .thumb {
				display: grid;
				flex: 0 0 auto;
				place-items: center;
				width: 3rem;
				height: 3rem;
				padding: 0;
				border: 1px solid var(--cms-color-border);
				border-radius: var(--cms-radius);
				background: var(--cms-color-surface);
				overflow: hidden;
				cursor: pointer;

				& img {
					width: 100%;
					height: 100%;
					object-fit: cover;
				}

				&.is-current {
					border-color: var(--cms-color-accent);
					box-shadow: 0 0 0 2px var(--cms-color-accent-ring);
				}
			}

			& .plate {
				font-size: 0.625rem;
				letter-spacing: 0.08em;
				text-transform: uppercase;
				color: var(--cms-color-text-subtle);
			}
		}

		/* The drawer parts, inline under the tiles or in the dialog alike. */
		:is(.cms-gallery, .cms-gallery-settings) {
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
