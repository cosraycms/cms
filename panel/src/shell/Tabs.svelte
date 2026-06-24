<script lang="ts">
	import type { Snippet } from 'svelte';

	type Props = {
		children: Snippet;
	};

	let { children }: Props = $props();
</script>

<div class="tabs cms-tabs">
	<nav aria-label="Tabs">
		{@render children()}
	</nav>
</div>

<style>
	@layer panel {
		.cms-tabs {
			margin-bottom: var(--space-6);
		}
	}
</style>
