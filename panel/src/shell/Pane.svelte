<script lang="ts">
	import type { Snippet } from 'svelte';

	type Props = {
		children: Snippet;
		class?: string | null;
	};

	let { children, class: classes = null }: Props = $props();
</script>

<div class="cms-pane {classes}">
	<div class="cms-pane-card">
		{@render children()}
	</div>
</div>

<style>
	@layer panel {
		.cms-pane {
			flex: 1 1 auto;
		}

		.cms-pane-card {
			max-width: 80rem;
			margin: 0 auto var(--space-12);
			padding-top: var(--space-2);
			padding-bottom: var(--space-4);
			border: 1px solid var(--color-neutral-200);
			background-color: var(--color-white);
			box-shadow: var(--shadow);
		}
	}
</style>
