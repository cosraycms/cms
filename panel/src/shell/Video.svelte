<script lang="ts">
	import type { FileItem } from '$types/data';
	import { _ } from '$lib/locale';
	import IcoTrash from '$shell/icons/IcoTrash.svelte';

	type Props = {
		path: string;
		file: FileItem;
		loading: boolean;
		upload: boolean;
		remove: () => void;
		class?: string;
	};

	let { path, file, loading, upload, remove, class: classes = '' }: Props = $props();

	let filename = $derived(file.file ?? '');
	let ext = $derived(filename.split('.').pop()?.toLowerCase());
</script>

<div class="video {classes}" class:empty={!file} class:upload>
	{#if loading}
		{_('Loading ...')}
	{:else}
		<video controls class="cms-video-player">
			<track kind="captions" />
			<source src="{path}/{filename}" type="video/{ext}" />
		</video>
		<div class="controls cms-video-controls">
			{#if remove}
				<button class="cms-video-remove" onclick={remove}>
					<span class="ico cms-video-ico">
						<IcoTrash />
					</span>
					<span class="icobtn cms-video-icobtn">{_('Löschen')}</span>
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
			border: 1px solid var(--color-neutral-300);
			background-color: var(--color-neutral-100);
			padding: var(--space-1);
			text-align: center;
		}

		.cms-video-player {
			width: 100%;
		}

		.cms-video-controls {
			margin-top: var(--space-4);
		}

		.cms-video-remove {
			color: var(--color-danger);
			border: none;
			background: transparent;
			cursor: pointer;
		}

		.ico {
			background-color: rgba(255, 255, 255, 0.8);
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
			font-size: var(--font-size-xs);
			color: var(--color-white);
			text-shadow:
				-1px 0 #000,
				0 1px #000,
				1px 0 #000,
				0 -1px #000;
		}

		.cms-video-ext {
			position: absolute;
			right: var(--space-1);
			bottom: var(--space-1);
			margin-right: var(--space-px);
			margin-bottom: var(--space-px);
			border-radius: var(--radius);
			background-color: var(--color-danger);
			padding: 0 var(--space-1);
			font-size: var(--font-size-xs);
			color: var(--color-white);
		}
	}
</style>
