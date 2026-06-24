<script lang="ts">
	import type { Component, Snippet } from 'svelte';
	import type { ModalOptions } from '.';

	import IcoTimes from '$shell/icons/IcoTimes.svelte';

	import { setContext } from 'svelte';

	let { children }: { children: Snippet } = $props();
	let Content: null | Component = $state(null);
	let componentProps: object = $state({});
	let css: string = $state('');
	let options = $state<ModalOptions>({});

	function open(content: Component, attributes: object = {}, opts: ModalOptions = {}) {
		Content = content;
		componentProps = attributes;
		options = opts;
	}

	function close() {
		Content = null;
	}

	setContext('modal', { open, close });
</script>

{#if Content}
	<div class="modal cms-modal-overlay">
		<div class="modal-container cms-modal-container" style={css}>
			{#if !options.hideClose}
				<button class="cms-modal-close" onclick={close} aria-label="close">
					<span>
						<IcoTimes />
					</span>
				</button>
			{/if}
			<Content {...componentProps} />
		</div>
	</div>
{/if}
{@render children()}

<style>
	@layer panel {
		.modal-container {
			background-color: var(--cms-color-white, #fff);
		}
	}
</style>
