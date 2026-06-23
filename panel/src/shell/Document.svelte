<script lang="ts">
	import type { Snippet } from 'svelte';

	type Props = {
		children: Snippet;
	};

	let { children }: Props = $props();
</script>

<div class="cms-document">
	<div class="cms-document-inner">
		{@render children()}
	</div>
</div>

<style lang="postcss">
	.cms-document {
		flex: 1 1 auto;
		overflow-y: auto;
	}

	.cms-document-inner {
		margin: 0 auto;
		width: 100%;
		max-width: var(--cms-size-7xl);
		padding: 0 var(--cms-space-8);
	}
</style>
