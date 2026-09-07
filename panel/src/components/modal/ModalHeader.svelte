<script lang="ts">
	import type { Snippet } from 'svelte';
	import Icon from '$components/Icon.svelte';

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
			<Icon name="x-lg" />
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
