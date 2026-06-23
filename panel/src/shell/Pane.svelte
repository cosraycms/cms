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

<style lang="postcss">
	.cms-pane {
		flex: 1 1 auto;
	}

	.cms-pane-card {
		max-width: 80rem;
		margin: 0 auto var(--cms-space-12);
		padding-top: var(--cms-space-2);
		padding-bottom: var(--cms-space-4);
		border: 1px solid var(--cms-color-neutral-200);
		background-color: var(--cms-color-white);
		box-shadow: var(--cms-shadow);
	}
</style>
