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

<style>
	@layer panel {
		.cms-document {
			flex: 1 1 auto;
			overflow-y: auto;
		}

		.cms-document-inner {
			margin: 0 auto;
			width: 100%;
			max-width: var(--node-max-width);
			padding: var(--space-8) var(--space-8) 0;
		}
	}
</style>
