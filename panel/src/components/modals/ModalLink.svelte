<script lang="ts">
	import type { LibraryItem } from '$lib/library';
	import type { NodeInfo } from '$components/NodeSearch.svelte';

	import { untrack } from 'svelte';
	import { __ } from '$lib/locale';
	import { ModalHeader, ModalBody, ModalFooter } from '$components/modal';
	import Icon from '$components/Icon.svelte';
	import Button from '$components/Button.svelte';
	import LibraryBrowser from '$components/LibraryBrowser.svelte';
	import NodeSearch from '$components/NodeSearch.svelte';

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
	const id = $props.id();

	type Tab = 'manually' | 'page' | 'images' | 'files';

	const tabs: { id: Tab; icon: string; label: string }[] = [
		{ id: 'manually', icon: 'link-45deg', label: __('link:manual') },
		{ id: 'page', icon: 'paragraph', label: __('node:page') },
		{ id: 'images', icon: 'image', label: __('media:images') },
		{ id: 'files', icon: 'file-earmark-richtext', label: __('media:files-documents') },
	];

	// Editing an existing link opens on the tab that matches its kind; an
	// asset link defaults to the files tab, which browses every kind. The
	// modal is remounted per open, so these props are a one-time seed
	// (untrack captures the current value without a reactive dependency).
	let currentTab = $state<Tab>(
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
			close();
			add(target, blank);
		}
	}

	function pickAsset(item: LibraryItem) {
		pickedAsset = item.uid;
	}

	function pickNode(item: NodeInfo) {
		pickedNode = item.uid;
	}

	function changeTab(tab: Tab) {
		return () => (currentTab = tab);
	}

	function keydown(event: KeyboardEvent & { currentTarget: HTMLElement }) {
		const index = tabs.findIndex((tab) => tab.id === currentTab);
		let next = -1;

		if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
			next = (index + 1) % tabs.length;
		} else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
			next = (index - 1 + tabs.length) % tabs.length;
		} else if (event.key === 'Home') {
			next = 0;
		} else if (event.key === 'End') {
			next = tabs.length - 1;
		}

		if (next < 0) {
			return;
		}

		event.preventDefault();
		currentTab = tabs[next].id;
		event.currentTarget
			.closest('[role="tablist"]')
			?.querySelectorAll<HTMLElement>('[role="tab"]')
			[next]?.focus();
	}
</script>

<ModalHeader>{__('richtext:add-link')}</ModalHeader>
<ModalBody>
	<div class="cms-modal-link-body">
		<div class="cms-tabs" role="tablist" aria-label={__('common:tabs')}>
			{#each tabs as tab (tab.id)}
				<button
					type="button"
					class="tab"
					role="tab"
					id={`${id}-tab-${tab.id}`}
					aria-selected={currentTab === tab.id}
					aria-controls={`${id}-panel`}
					tabindex={currentTab === tab.id ? 0 : -1}
					onclick={changeTab(tab.id)}
					onkeydown={keydown}
				>
					<Icon name={tab.icon} />
					<span>{tab.label}</span>
				</button>
			{/each}
		</div>
		<div
			class="files cms-modal-link-files"
			role="tabpanel"
			id={`${id}-panel`}
			aria-labelledby={`${id}-tab-${currentTab}`}
		>
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
						<input
							class="cms-input"
							type="text"
							data-dialog-focus
							aria-label={__('link:manual')}
							bind:value={url}
						/>
					</div>
				</div>
			{/if}
		</div>
	</div>
	<div class="cms-modal-link-target-wrap">
		<div class="cms-modal-link-target-row">
			<div class="cms-modal-link-target-input-wrap">
				<input id={`${id}-target`} type="checkbox" bind:checked={blank} class="cms-checkbox" />
			</div>
			<div class="cms-modal-link-target-label-wrap">
				<label for={`${id}-target`} class="cms-checkbox-label">
					{__('link:open-new-window')}
				</label>
			</div>
		</div>
	</div>
</ModalBody>
<ModalFooter>
	<Button variant="secondary" onclick={close}>
		{__('common:cancel')}
	</Button>
	<Button variant="primary" onclick={clickAdd} disabled={!canAdd}>
		{__('link:add')}
	</Button>
</ModalFooter>

<style>
	@layer panel {
		.cms-modal-link-body {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-4);
		}

		.cms-modal-link-files {
			max-height: 60vh;
			overflow-y: auto;
		}

		.cms-modal-link-manual-hint {
			margin-top: var(--cms-space-4);
		}

		.cms-modal-link-manual-input-wrap {
			margin-top: var(--cms-space-4);
		}

		.cms-modal-link-target-wrap {
			margin-top: var(--cms-space-4);
		}

		.cms-modal-link-target-row {
			position: relative;
			display: flex;
			align-items: flex-start;
		}

		.cms-modal-link-target-input-wrap {
			display: flex;
			height: var(--cms-space-6);
			align-items: center;
		}

		.cms-modal-link-target-label-wrap {
			margin-left: var(--cms-space-3);
			font-size: var(--cms-font-size-sm);
			line-height: 1.5rem;
		}
	}
</style>
