<script lang="ts">
	import type { Snippet } from 'svelte';

	import { close, modal } from '$lib/modal';
	import IcoTimes from '$shell/icons/IcoTimes.svelte';

	let { children }: { children: Snippet } = $props();

	function renderInto(host: HTMLElement, render: (host: HTMLElement) => (() => void) | void) {
		const cleanup = render(host);

		return {
			destroy() {
				cleanup?.();
			},
		};
	}
</script>

{#if $modal}
	<div class="modal cms-modal-overlay">
		<div class="modal-container cms-modal-container">
			{#if !$modal.options.hideClose}
				<button class="cms-modal-close" onclick={close} aria-label="close">
					<span>
						<IcoTimes />
					</span>
				</button>
			{/if}
			{#if $modal.kind === 'component'}
				{@const Content = $modal.component}
				<Content {...$modal.props} />
			{:else}
				<div class="cms-modal-element" use:renderInto={$modal.render}></div>
			{/if}
		</div>
	</div>
{/if}
{@render children()}

<style>
	@layer panel {
		.modal-container {
			background-color: var(--color-white, #fff);
		}
	}
</style>
