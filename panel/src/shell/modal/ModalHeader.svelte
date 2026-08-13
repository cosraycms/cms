<script lang="ts">
	import type { Snippet } from 'svelte';

	import { __ } from '$lib/locale';

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

<div class="modal-header header {classes}">
	<h3 class="modal-header-title title">
		{@render children()}
	</h3>
	{#if close}
		<button
			type="button"
			class="modal-close-button close-button"
			data-modal-hide="default-modal"
			onclick={close}
		>
			<svg
				class="close-icon"
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
			<span class="sr-only">{__('common:close')}</span>
		</button>
	{/if}
</div>

<style>
	@layer panel {
		.modal-close-button {
			color: var(--cms-color-text-subtle);

			&:hover {
				filter: brightness(90%);
			}
		}
	}
</style>
