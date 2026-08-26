<script lang="ts">
	import type { FileItem } from '$types/data';
	import { __ } from '$lib/locale';
	import { useAssets } from '$lib/assets';
	import IcoTrash from '$components/icons/IcoTrash.svelte';

	type Props = {
		file: FileItem;
		loading: boolean;
		upload: boolean;
		remove: () => void;
		class?: string;
	};

	let { file, loading, upload, remove, class: classes = '' }: Props = $props();

	const assets = useAssets();

	let info = $derived($assets[file.uid ?? '']);
	let filename = $derived(info?.filename ?? '');
	let ext = $derived(filename.split('.').pop()?.toLowerCase());
</script>

<div class="video {classes}" class:empty={!file} class:upload>
	{#if loading}
		{__('common:loading')}
	{:else}
		<video controls class="cms-video-player">
			<track kind="captions" />
			<source src={info?.url ?? ''} type="video/{ext}" />
		</video>
		<div class="controls cms-video-controls">
			{#if remove}
				<button class="cms-video-remove" onclick={remove}>
					<span class="ico cms-video-ico">
						<IcoTrash />
					</span>
					<span class="icobtn cms-video-icobtn">{__('common:delete')}</span>
				</button>
			{/if}
		</div>
	{/if}
	{#if ext}
		<span class="cms-video-ext">
			{ext.toUpperCase()}
		</span>
	{/if}
</div>

<style>
	@layer panel {
		.video {
			position: relative;
			width: 100%;
			border: 1px solid var(--cms-color-border-strong);
			background-color: var(--cms-color-surface-sunken);
			padding: var(--cms-space-1);
			text-align: center;
		}

		.cms-video-player {
			width: 100%;
		}

		.cms-video-controls {
			margin-top: var(--cms-space-4);
		}

		.cms-video-remove {
			color: var(--cms-color-danger);
			border: none;
			background: transparent;
			cursor: pointer;
		}

		.ico {
			/* Carries a status-coloured icon, so the disc has to flip with it. */
			background-color: color-mix(in srgb, var(--cms-color-surface) 80%, transparent);
			border-radius: 100%;
			height: 2.5rem;
			width: 2.5rem;
			font-size: 1.6rem;

			:global(svg) {
				height: 1.25rem;
			}
		}

		.cms-video-ico {
			display: flex;
			align-items: center;
			justify-content: center;
		}

		.icobtn {
			text-align: center;
			font-size: var(--cms-font-size-xs);
			/* Sits on arbitrary media with a dark outline: stays white in both themes. */
			color: var(--cms-color-white);
			text-shadow:
				-1px 0 #000,
				0 1px #000,
				1px 0 #000,
				0 -1px #000;
		}

		.cms-video-ext {
			position: absolute;
			right: var(--cms-space-1);
			bottom: var(--cms-space-1);
			margin-right: var(--cms-space-px);
			margin-bottom: var(--cms-space-px);
			border-radius: var(--cms-radius);
			background-color: var(--cms-color-danger);
			padding: 0 var(--cms-space-1);
			font-size: var(--cms-font-size-xs);
			color: var(--cms-color-text-on-fill);
		}
	}
</style>
