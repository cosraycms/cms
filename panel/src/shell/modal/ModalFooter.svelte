<script lang="ts">
	import type { Snippet } from 'svelte';

	let { children, class: classes = '' }: { children: Snippet; class?: string } = $props();
</script>

<div class="modal-footer cms-modal-footer {classes}">
	{@render children()}
</div>

<style lang="postcss">
	.modal-footer {
		border-color: var(--cms-border-color);
	}
</style>
