<script lang="ts">
	import type { Node } from '$types/data';

	import { _ } from '$lib/locale';
	import { broadcastCancel, dirty } from '$lib/state';
	import { previewRoutePaths, routePathPreviewPayload } from '$lib/urlpaths';
	import Document from '$shell/Document.svelte';
	import Pane from '$shell/Pane.svelte';
	import Tabs from '$shell/Tabs.svelte';
	import ButtonMenu from '$shell/ButtonMenu.svelte';
	import Button from '$shell/Button.svelte';
	import IcoSave from '$shell/icons/IcoSave.svelte';
	import ButtonMenuEntry from '$shell/ButtonMenuEntry.svelte';
	import Content from '$shell/Content.svelte';
	import Settings from '$shell/Settings.svelte';
	import Published from '$shell/Published.svelte';

	type Props = {
		node: Node;
		save: (published: boolean) => Promise<void>;
		saveAndClose: () => Promise<void>;
		fields: string[];
	};

	let { node = $bindable(), save, saveAndClose, fields }: Props = $props();

	let activeTab = $state('content');
	let pathPreviewRequest = 0;

	function changeTab(tab: string) {
		return () => {
			activeTab = tab;
		};
	}

	function cancel() {
		broadcastCancel();
	}

	$effect(() => {
		if (!node.route) {
			return;
		}

		const request = ++pathPreviewRequest;
		const type = node.type.handle;
		const payload = routePathPreviewPayload(node);

		void previewRoutePaths(type, payload)
			.then(paths => {
				if (request === pathPreviewRequest) {
					node.generatedPaths = paths ?? {};
				}
			})
			.catch(() => {
				if (request === pathPreviewRequest) {
					node.generatedPaths = {};
				}
			});
	});
</script>

<div class="cms-embedded-node">
	{#if fields.length === 0}
		<div class="embed-control-bar cms-embedded-control-bar">
			<div class="cms-embedded-control-inner">
				<div class="embed-status-bar cms-embedded-status-bar">
					{#if node.type.renderable}
						<span class="cms-embedded-status-pill">
							<Published
								published={node.published}
								large />
						</span>
					{/if}
					{#if $dirty}
						<span class="dirty-indicator cms-embedded-dirty-indicator">!</span>
					{/if}
				</div>
				<ButtonMenu
					class="primary"
					icon={IcoSave}
					onclick={() => save(false)}
					label={_('Speichern')}>
					{#snippet children(closeMenu)}
						<ButtonMenuEntry
							onclick={() => {
								save(true);
								closeMenu();
							}}>
							{_('Speichern und veröffentlichen')}
						</ButtonMenuEntry>
					{/snippet}
				</ButtonMenu>
			</div>
		</div>
	{/if}
	<Document>
		{#if fields.length > 0}
			<div class="cms-embedded-pane">
				<Pane>
					<Content
						bind:fields={node.fields}
						visibleFields={fields}
						bind:node />
				</Pane>
			</div>
			<div class="cms-embedded-actions">
				<Button
					onclick={cancel}
					class="danger">
					{_('Abbrechen')}
				</Button>
				<Button onclick={saveAndClose}>
					{_('Speichern')}
				</Button>
			</div>
		{:else}
			<Tabs>
				<button
					onclick={changeTab('content')}
					class:active={activeTab === 'content'}
					class="tab">
					{_('Inhalt')}
				</button>
				{#if node.type.routable || node.type.renderable}
					<button
						onclick={changeTab('settings')}
						class:active={activeTab === 'settings'}
						class="tab">
						{_('Einstellungen')}
					</button>
				{/if}
			</Tabs>
			<Pane>
				{#if activeTab === 'content'}
					<Content
						bind:fields={node.fields}
						bind:node />
				{:else}
					<Settings bind:node />
				{/if}
			</Pane>
		{/if}
	</Document>
</div>

<style lang="postcss">
	.cms-embedded-node {
		display: flex;
		min-height: 100vh;
		flex-direction: column;
	}

	.cms-embedded-control-bar {
		position: sticky;
		top: 0;
		border-bottom: 1px solid var(--cms-color-neutral-300);
		background-color: var(--cms-color-white);
		padding: var(--cms-space-4) 0;
	}

	.cms-embedded-control-inner {
		margin: 0 auto;
		display: flex;
		width: 100%;
		max-width: var(--cms-size-7xl);
		flex-direction: row;
		padding: 0 var(--cms-space-8);
	}

	.cms-embedded-status-bar {
		display: flex;
		flex: 1 1 auto;
		flex-direction: row;
		align-items: center;
		justify-content: flex-start;
	}

	.cms-embedded-status-pill {
		display: inline-flex;
		align-items: center;
	}

	.cms-embedded-dirty-indicator {
		margin-left: var(--cms-space-3);
		border-radius: var(--cms-radius-full);
		background-color: var(--cms-color-danger-700);
		padding: 0 var(--cms-space-2) var(--cms-space-px);
		font-size: var(--cms-font-size-sm);
		font-weight: 700;
		color: var(--cms-color-white);
	}

	.cms-embedded-pane {
		margin-top: var(--cms-space-6);
	}

	.cms-embedded-actions {
		display: flex;
		justify-content: flex-end;
		gap: var(--cms-space-2);
		margin-top: calc(var(--cms-space-4) * -1);
	}
</style>
