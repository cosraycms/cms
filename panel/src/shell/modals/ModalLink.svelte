<script lang="ts">
	import type { LibraryItem } from '$shell/LibraryBrowser.svelte';
	import type { NodeInfo } from '$shell/NodeSearch.svelte';

	import { untrack } from 'svelte';
	import { _ } from '$lib/locale';
	import { ModalHeader, ModalBody, ModalFooter } from '$shell/modal';
	import IcoDocument from '$shell/icons/IcoDocument.svelte';
	import IcoImage from '$shell/icons/IcoImage.svelte';
	import IcoLink from '$shell/icons/IcoLink.svelte';
	import IcoParagraph from '$shell/icons/IcoParagraph.svelte';
	import Button from '$shell/Button.svelte';
	import LibraryBrowser from '$shell/LibraryBrowser.svelte';
	import NodeSearch from '$shell/NodeSearch.svelte';

	// Exactly one of href/node/asset carries the target; the active tab
	// decides which. Matches the richtext `link` mark's attrs.
	type LinkTarget = { href?: string; node?: string; asset?: string };

	type Props = {
		close: () => void;
		add: (target: LinkTarget, blank: boolean) => void;
		href?: string;
		node?: string;
		asset?: string;
		blank: boolean;
	};

	let { close, add, href = '', node = '', asset = '', blank = $bindable() }: Props = $props();

	// Editing an existing link opens on the tab that matches its kind; an
	// asset link defaults to the files tab, which browses every kind. The
	// modal is remounted per open, so these props are a one-time seed
	// (untrack captures the current value without a reactive dependency).
	let currentTab = $state(
		untrack(() => (node !== '' ? 'page' : asset !== '' ? 'files' : 'manually')),
	);

	let url = $state(untrack(() => href));
	let pickedNode = $state(untrack(() => node));
	let pickedAsset = $state(untrack(() => asset));

	const canAdd = $derived(
		currentTab === 'page'
			? pickedNode !== ''
			: currentTab === 'images' || currentTab === 'files'
				? pickedAsset !== ''
				: url !== '',
	);

	function clickAdd() {
		let target: LinkTarget | null = null;

		if (currentTab === 'page') {
			target = pickedNode !== '' ? { node: pickedNode } : null;
		} else if (currentTab === 'images' || currentTab === 'files') {
			target = pickedAsset !== '' ? { asset: pickedAsset } : null;
		} else {
			target = url !== '' ? { href: url } : null;
		}

		if (target) {
			add(target, blank);
			close();
		}
	}

	function pickAsset(item: LibraryItem) {
		pickedAsset = item.uid;
	}

	function pickNode(item: NodeInfo) {
		pickedNode = item.uid;
	}

	function changeTab(tab: string) {
		return () => (currentTab = tab);
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
					<button class="tab" class:active={currentTab === 'page'} onclick={changeTab('page')}>
						<IcoParagraph />
						<span>{_('Seite')}</span>
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
			{#if currentTab === 'page'}
				{#key currentTab}
					<NodeSearch pick={pickNode} selected={pickedNode || null} />
				{/key}
			{:else if currentTab === 'images'}
				{#key currentTab}
					<LibraryBrowser kind="image" pick={pickAsset} selected={pickedAsset} />
				{/key}
			{:else if currentTab === 'files'}
				{#key currentTab}
					<LibraryBrowser pick={pickAsset} selected={pickedAsset} />
				{/key}
			{:else}
				<div>
					<div class="cms-modal-link-manual-hint">
						{_('Bitte eine gültige URL eingeben')}
					</div>
					<div class="cms-modal-link-manual-input-wrap">
						<input class="cms-input" type="text" bind:value={url} />
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
		<Button variant="danger" onclick={close}>
			{_('Abbrechen')}
		</Button>
		<Button variant="primary" onclick={clickAdd} disabled={!canAdd}>
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
			max-height: 60vh;
			overflow-y: auto;
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
