<script lang="ts">
	import type { Snippet } from 'svelte';

	let {
		close,
		children,
		class: classes = '',
	}: {
		children: Snippet;
		class?: string;
		close?: () => void;
	} = $props();
</script>

<div class="modal-header cms-modal-header {classes}">
	<h3 class="modal-header-title cms-modal-header-title">
		{@render children()}
	</h3>
	{#if close}
		<button
			type="button"
			class="modal-close-button cms-modal-header-close"
			data-modal-hide="default-modal"
			onclick={close}
		>
			<svg
				class="cms-modal-header-close-icon"
				aria-hidden="true"
				xmlns="http://www.w3.org/2000/svg"
				fill="none"
				viewBox="0 0 14 14"
			>
				<path
					stroke="currentColor"
					stroke-linecap="round"
					stroke-linejoin="round"
					stroke-width="2"
					d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"
				/>
			</svg>
			<span class="sr-only">Close modal</span>
		</button>
	{/if}
</div>

<style>
	.modal-close-button {
		color: var(--cms-color-neutral-500);

		&:hover {
			filter: brightness(90%);
		}
	}
</style>
