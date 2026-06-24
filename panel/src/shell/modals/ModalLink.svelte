<script lang="ts">
	import { _ } from '$lib/locale';
	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import { currentNode as node, currentFields as fields } from '$lib/state';
	import IcoDocument from '$shell/icons/IcoDocument.svelte';
	import IcoImage from '$shell/icons/IcoImage.svelte';
	import IcoLink from '$shell/icons/IcoLink.svelte';
	import File from './ModalLinkFile.svelte';
	import Image from './ModalLinkImage.svelte';
	import Button from '$shell/Button.svelte';
	import type { FileItem } from '$types/data';

	type Props = {
		close: () => void;
		add: (value: string, blank: boolean) => void;
		value: string;
		blank: boolean;
	};

	let { close, add, value = $bindable(), blank = $bindable() }: Props = $props();

	let currentTab = $state('manually');

	function clickAdd() {
		if (value) {
			add(value, blank);
			close();
		}
	}

	function clickFile(path: string) {
		value = path;
	}

	function changeTab(tab: string) {
		return () => (currentTab = tab);
	}

	function media(field: string): FileItem[] {
		const content = $node?.content[field];

		if (!content || !('value' in content)) {
			return [];
		}

		const value = content.value.zxx;

		return Array.isArray(value) ? (value as FileItem[]) : [];
	}
</script>

<ModalHeader>{_('Add link')}</ModalHeader>
<ModalBody>
	<div class="cms-modal-link-body">
		<div class="tabs">
			<div class="cms-modal-link-tabs-frame">
				<nav class="cms-modal-link-tabs-nav" aria-label="Tabs">
					<button
						class="tab"
						class:active={currentTab === 'manually'}
						onclick={changeTab('manually')}
					>
						<IcoLink />
						<span>{_('Manueller Link')}</span>
					</button>
					<button class="tab" class:active={currentTab === 'images'} onclick={changeTab('images')}>
						<IcoImage />
						<span>{_('Bilder')}</span>
					</button>
					<button class="tab" class:active={currentTab === 'files'} onclick={changeTab('files')}>
						<IcoDocument />
						<span>{_('Dateien/Dokumente')}</span>
					</button>
				</nav>
			</div>
		</div>
		<div class="files cms-modal-link-files">
			{#if currentTab === 'images'}
				{#if $fields && $node}
					<div class="cms-modal-link-images-grid">
						{#each $fields as field (field)}
							{#if field.type === 'Cosray\\Field\\Image'}
								{#each media(field.name) as file}
									{#if file.file}
										<Image node={$node.uid} file={file.file} {clickFile} bind:current={value} />
									{/if}
								{/each}
							{/if}
						{/each}
					</div>
				{/if}
			{:else if currentTab === 'files'}
				{#if $fields && $node}
					<div>
						{#each $fields as field (field)}
							{#if field.type === 'Cosray\\Field\\File'}
								{#each media(field.name) as file}
									{#if file.file}
										<File node={$node.uid} file={file.file} {clickFile} bind:current={value} />
									{/if}
								{/each}
							{/if}
						{/each}
					</div>
				{/if}
			{:else}
				<div>
					<div class="cms-modal-link-manual-hint">
						{_('Bitte eine gültige URL eingeben')}
					</div>
					<div class="cms-modal-link-manual-input-wrap">
						<input class="cms-input" type="text" bind:value />
					</div>
				</div>
			{/if}
		</div>
	</div>
	<div class="cms-modal-link-target-wrap">
		<div class="cms-modal-link-target-row">
			<div class="cms-modal-link-target-input-wrap">
				<input
					id="modallink_target"
					aria-describedby="comments-description"
					name="modallink_target"
					type="checkbox"
					bind:checked={blank}
					class="cms-checkbox"
				/>
			</div>
			<div class="cms-modal-link-target-label-wrap">
				<label for="modallink_target" class="cms-checkbox-label">
					{_('In neuem Fenster öffnen')}
				</label>
			</div>
		</div>
	</div>
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button class="danger" onclick={close}>
			{_('Abbrechen')}
		</Button>
		<Button class="primary" onclick={clickAdd} disabled={!value}>
			{_('Link hinzufügen')}
		</Button>
	</div>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-link-body {
			display: flex;
			flex-direction: column;
			gap: var(--space-4);
		}

		.cms-modal-link-tabs-frame {
			border-bottom: 1px solid var(--color-neutral-200);
		}

		.cms-modal-link-tabs-nav {
			display: flex;
			flex-wrap: wrap;
			gap: var(--space-2);
		}

		.cms-modal-link-files {
			max-height: 50vh;
			overflow-y: auto;
		}

		.cms-modal-link-images-grid {
			display: flex;
			flex-direction: row;
			flex-wrap: wrap;
			gap: var(--space-2);
		}

		.cms-modal-link-manual-hint {
			margin-top: var(--space-4);
		}

		.cms-modal-link-manual-input-wrap {
			margin-top: var(--space-4);
		}

		.cms-modal-link-target-wrap {
			margin-top: var(--space-4);
		}

		.cms-modal-link-target-row {
			position: relative;
			display: flex;
			align-items: flex-start;
		}

		.cms-modal-link-target-input-wrap {
			display: flex;
			height: var(--space-6);
			align-items: center;
		}

		.cms-modal-link-target-label-wrap {
			margin-left: var(--space-3);
			font-size: var(--font-size-sm);
			line-height: 1.5rem;
		}
	}
</style>
