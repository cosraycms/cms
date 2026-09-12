<script lang="ts">
	import type { FileItem, LocaleMap } from '$types/data';

	import { ZXX } from '$types/data';
	import { useAssets } from '$lib/assets';
	import { fileIcon, humanSize } from '$lib/library';
	import { __ } from '$lib/locale';
	import Icon from '$components/Icon.svelte';

	type Props = {
		item: FileItem;
		translate: boolean;
		contentLocale: string;
		loading?: boolean;
		/** Shown, not editable: no actions, as in a fallback preview or a read-only field. */
		inert?: boolean;
		edit?: () => void;
		remove?: () => void;
	};

	let {
		item,
		translate,
		contentLocale,
		loading = false,
		inert = false,
		edit,
		remove,
	}: Props = $props();

	const assets = useAssets();

	let info = $derived($assets[item.uid ?? '']);
	let filename = $derived(info?.filename ?? item.uid ?? '');
	let thumb = $derived(info?.kind === 'image' ? (info.thumbUrl ?? info.url) : '');
	let key = $derived(translate ? contentLocale : ZXX);
	let title = $derived.by(() => {
		const titles = item.meta?.title as LocaleMap<string> | undefined;

		return titles?.[key] || titles?.[ZXX] || '';
	});
	let size = $derived(typeof info?.bytes === 'number' ? humanSize(info.bytes) : '');
</script>

<div class="cms-file-row">
	<span class="icon">
		{#if thumb}
			<img src={thumb} alt="" loading="lazy" />
		{:else}
			<Icon name={fileIcon({ filename, mime: info?.mime })} />
		{/if}
	</span>
	<span class="name">
		{#if info?.url}
			<a class="filename" href={info.url} target="_blank" rel="noopener" title={filename}>
				{filename}
			</a>
		{:else}
			<span class="filename" title={filename}>{filename}</span>
		{/if}
		{#if title}
			<span class="title" {title}>{title}</span>
		{/if}
	</span>
	<span class="size">{loading ? __('upload:uploading') : size}</span>
	{#if !inert}
		<button
			type="button"
			class="tool edit"
			title={__('common:edit')}
			aria-label={__('common:edit')}
			onclick={edit}
		>
			<Icon name="pencil" />
		</button>
		<button
			type="button"
			class="tool remove"
			title={__('common:remove')}
			aria-label={__('common:remove')}
			onclick={remove}
		>
			<Icon name="x-lg" />
		</button>
	{/if}
</div>

<style>
	@layer panel {
		.cms-file-row {
			display: flex;
			align-items: center;
			gap: var(--cms-space-3);
			min-height: 2.75rem;
			padding: var(--cms-space-1-5) var(--cms-space-2) var(--cms-space-1-5) var(--cms-space-3);
			border-bottom: 1px solid var(--cms-color-border);
			font-size: var(--cms-font-size-sm);

			&:last-child {
				border-bottom: 0;
			}

			&:hover {
				background: var(--cms-color-hover);
			}

			& .icon {
				display: grid;
				flex-shrink: 0;
				place-items: center;
				width: 1.75rem;
				height: 1.75rem;
				color: var(--cms-color-text-muted);

				& :global(svg) {
					width: 1.25rem;
					height: 1.25rem;
				}

				& img {
					width: 100%;
					height: 100%;
					border-radius: var(--cms-radius-sm);
					object-fit: cover;
				}
			}

			& .name {
				display: flex;
				flex: 1 1 auto;
				flex-wrap: wrap;
				align-items: baseline;
				gap: 0 var(--cms-space-3);
				min-width: 0;
			}

			& .filename {
				min-width: 0;
				max-width: 100%;
				overflow: hidden;
				color: var(--cms-color-text);
				text-decoration: none;
				text-overflow: ellipsis;
				white-space: nowrap;

				&:is(a):hover {
					text-decoration: underline;
					text-underline-offset: 3px;
				}
			}

			& .title {
				min-width: 0;
				overflow: hidden;
				color: var(--cms-color-text-subtle);
				font-size: var(--cms-font-size-xs);
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			& .size {
				flex-shrink: 0;
				color: var(--cms-color-text-subtle);
				font-size: var(--cms-font-size-xs);
				font-variant-numeric: tabular-nums;
				white-space: nowrap;
			}

			& .tool {
				display: grid;
				flex-shrink: 0;
				place-items: center;
				width: 1.75rem;
				height: 1.75rem;
				padding: 0;
				border: 0;
				border-radius: var(--cms-radius);
				background: transparent;
				color: var(--cms-color-text-muted);
				cursor: pointer;

				& :global(svg) {
					width: 0.875rem;
					height: 0.875rem;
				}

				&:hover {
					background: var(--cms-color-surface);
					color: var(--cms-color-text);
				}

				&:focus-visible {
					outline: var(--cms-focus-outline);
					outline-offset: var(--cms-focus-offset);
				}
			}
		}
	}
</style>
