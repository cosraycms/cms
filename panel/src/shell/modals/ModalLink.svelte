<script lang="ts">
	import type { LibraryItem } from '$shell/LibraryBrowser.svelte';
	import type { NodeInfo } from '$shell/NodeSearch.svelte';

	import { untrack } from 'svelte';
	import { __ } from '$lib/locale';
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

<ModalHeader>{__('richtext:add-link')}</ModalHeader>
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
						<span>{__('link:manual')}</span>
					</button>
					<button class="tab" class:active={currentTab === 'page'} onclick={changeTab('page')}>
						<IcoParagraph />
						<span>{__('node:page')}</span>
					</button>
					<button class="tab" class:active={currentTab === 'images'} onclick={changeTab('images')}>
						<IcoImage />
						<span>{__('media:images')}</span>
					</button>
					<button class="tab" class:active={currentTab === 'files'} onclick={changeTab('files')}>
						<IcoDocument />
						<span>{__('media:files-documents')}</span>
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
						{__('link:invalid-url')}
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
					{__('link:open-new-window')}
				</label>
			</div>
		</div>
	</div>
</ModalBody>
<ModalFooter>
	<div class="controls">
		<Button variant="danger" onclick={close}>
			{__('common:cancel')}
		</Button>
		<Button variant="primary" onclick={clickAdd} disabled={!canAdd}>
			{__('link:add')}
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
