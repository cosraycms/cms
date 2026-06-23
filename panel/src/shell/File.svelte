<script lang="ts">
	import type { FileItem } from '$types/data';
	import { _ } from '$lib/locale';
	import { system } from '$lib/sys';
	import IcoDocument from '$shell/icons/IcoDocument.svelte';
	import IcoDownload from '$shell/icons/IcoDownload.svelte';
	import IcoTrash from '$shell/icons/IcoTrash.svelte';
	import IcoPencil from '$shell/icons/IcoPencil.svelte';

	type Props = {
		path: string;
		asset: FileItem;
		remove: () => void;
		edit: () => void;
		loading: boolean;
	};

	let { path, asset, remove, edit, loading }: Props = $props();

	let title = $derived(getTitle(asset));

	function getTitle(asset: FileItem) {
		const value = asset.meta?.title;

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

{#if asset}
	<div class="file cms-file">
		<IcoDocument />
		<div class="cms-file-meta">
			<b class="cms-file-name">{asset.file}</b>
			<span class="cms-file-title">{title}</span>
		</div>
		{#if loading}
			<div>Loading ...</div>
		{/if}
		<IcoDownload />
		<a href={`${path}/${asset.file}`} target="_blank" class="cms-file-download">
			{_('Datei herunterladen')}
		</a>

		<button onclick={edit} class="cms-file-action cms-file-action-edit">
			<span class="cms-file-action-icon">
				<IcoPencil />
			</span>
		</button>

		<button onclick={remove} class="cms-file-action cms-file-action-remove">
			<span class="cms-file-action-icon">
				<IcoTrash />
			</span>
		</button>
	</div>
{/if}

<style>
	.cms-file {
		position: relative;
		display: flex;
		width: 100%;
		flex-direction: row;
		align-items: center;
		border: 1px solid var(--cms-color-neutral-300);
		border-radius: var(--cms-radius-lg);
		background-color: var(--cms-color-neutral-100);
		padding: var(--cms-space-2) var(--cms-space-4);
		text-align: center;
		color: var(--cms-color-neutral-600);
	}

	.cms-file-meta {
		flex: 1 1 auto;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		padding-left: var(--cms-space-3);
		text-align: left;
	}

	.cms-file-name {
		font-weight: 600;
	}

	.cms-file-title {
		display: inline-block;
		padding-left: var(--cms-space-4);
	}

	.cms-file-download {
		display: inline-block;
		padding-left: var(--cms-space-2);
	}

	.cms-file-action {
		border: none;
		background: transparent;
		cursor: pointer;
	}

	.cms-file-action-edit {
		color: var(--cms-color-info-700);
	}

	.cms-file-action-remove {
		color: var(--cms-color-danger-700);
	}

	.cms-file-action-icon {
		margin-left: var(--cms-space-4);
		display: flex;
		height: var(--cms-space-4);
		width: var(--cms-space-4);
		align-items: center;
	}
</style>
