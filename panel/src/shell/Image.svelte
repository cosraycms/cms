<script lang="ts">
	import type { FileItem } from '$types/data';
	import type { ModalFunctions } from '$shell/modal';

	import { getContext, type Component } from 'svelte';
	import { system } from '$lib/sys';
	import { _ } from '$lib/locale';
	import IcoTrash from '$shell/icons/IcoTrash.svelte';
	import IcoEye from '$shell/icons/IcoEye.svelte';
	import IcoPencil from '$shell/icons/IcoPencil.svelte';
	import ImagePreview from '$shell/ImagePreview.svelte';

	type Props = {
		path: string;
		image: FileItem;
		loading: boolean;
		upload: boolean;
		multiple: boolean;
		remove: () => void;
		edit: () => void;
		class?: string;
	};

	let {
		path,
		image,
		loading,
		upload,
		multiple,
		remove,
		edit,
		class: classes = '',
	}: Props = $props();

	let { open, close } = getContext<ModalFunctions>('modal');

	let hover = $state(false);
	let filename = $derived(image.file ?? '');
	let ext = $derived(filename.split('.').pop()?.toLowerCase());
	let orig = $derived(`${path}/${filename}`);
	let thumb = $derived(ext === 'svg' ? orig : `${path}/${thumbIt(filename)}`);
	let title = $derived(getTitle(image, 'title') || getTitle(image, 'alt'));

	function preview() {
		open(
			ImagePreview as Component,
			{
				close,
				image: orig,
			},
			{},
		);
	}

	function thumbIt(image: string) {
		return image + '?resize=width&w=400';
	}

	function getTitle(image: FileItem, key: string) {
		const value = image.meta?.[key];

		if (value?.zxx) {
			return value.zxx;
		}

		for (const locale of $system.locales) {
			if (value?.[locale.id]) {
				return value[locale.id];
			}
		}

		return '';
	}
</script>

<div class="image {classes}" class:empty={!image} class:upload class:multiple class:hover>
	{#if loading}
		{_('Loading ...')}
	{:else}
		<img src={thumb} alt={_('Vorschau')} />
		<div class="overlay">
			{#if remove}
				<button class="overlay-action overlay-action-danger" onclick={remove}>
					<span class="ico">
						<IcoTrash />
					</span>
					<span class="icobtn">{_('Löschen')}</span>
				</button>
			{/if}
			<button class="overlay-action overlay-action-primary" onclick={preview}>
				<span class="ico">
					<IcoEye />
				</span>
				<span class="icobtn">{_('Vorschau')}</span>
			</button>
			<button class="overlay-action overlay-action-primary" onclick={edit}>
				<span class="ico">
					<IcoPencil />
				</span>
				<span class="icobtn">{_('Titel')}</span>
			</button>
		</div>
	{/if}
	{#if title}
		<button class="title image-title" onclick={edit}>
			{title}
		</button>
	{/if}
	{#if ext}
		<span class="image-ext">
			{ext.toUpperCase()}
		</span>
	{/if}
</div>

<style>
	@layer panel {
		.image {
			position: relative;
			border: 1px solid var(--cms-color-neutral-300);
			background-color: var(--cms-color-neutral-100);
			padding: var(--cms-space-1);
			text-align: center;
		}

		button.image-title {
			position: absolute;
			left: var(--cms-space-1);
			bottom: var(--cms-space-1);
			margin-left: var(--cms-space-px);
			margin-bottom: var(--cms-space-px);
			padding: 0 var(--cms-space-1);
			border-radius: var(--cms-radius);
			background-color: var(--cms-color-white);
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-neutral-600);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
			max-width: 8rem;
		}

		.image-ext {
			position: absolute;
			right: var(--cms-space-1);
			bottom: var(--cms-space-1);
			margin-right: var(--cms-space-px);
			margin-bottom: var(--cms-space-px);
			border-radius: var(--cms-radius);
			background-color: var(--cms-color-danger-700);
			padding: 0 var(--cms-space-1);
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-white);
		}

		.image:hover .overlay,
		.image.hover .overlay {
			visibility: visible;
			opacity: 1;
		}
		.image.upload {
			display: flex;
			width: 100%;
			flex-shrink: 1;
			align-items: center;
			justify-content: center;
			max-height: 13rem;
			min-height: 6rem;
		}
		.image.multiple.upload {
			height: 11.13rem;
			width: 11.13rem;
			max-width: 11.13rem;
			max-height: 11.13rem;
		}

		img {
			max-width: 100%;
			max-height: 100%;
		}

		.overlay {
			position: absolute;
			top: var(--cms-space-1);
			right: var(--cms-space-1);
			bottom: var(--cms-space-1);
			left: var(--cms-space-1);
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: center;
			gap: var(--cms-space-2);
			visibility: hidden;
			opacity: 0;
			transition:
				visibility 0.1s,
				opacity 0.2s linear;
			background: rgba(0, 0, 0, 0.3);
		}

		.overlay-action {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			cursor: pointer;
		}

		.overlay-action-danger {
			color: var(--cms-color-danger-700);
		}

		.overlay-action-primary {
			color: var(--cms-color-info-700);
		}

		.ico {
			display: flex;
			align-items: center;
			justify-content: center;
			background-color: rgba(255, 255, 255, 0.8);
			border-radius: 100%;
			height: 2.5rem;
			width: 2.5rem;
			font-size: 1.6rem;

			:global(svg) {
				height: 1.25rem;
			}
		}

		.icobtn {
			text-align: center;
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-white);
			text-shadow:
				-1px 0 #000,
				0 1px #000,
				1px 0 #000,
				0 -1px #000;
		}
	}
</style>
