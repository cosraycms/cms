<script lang="ts">
	import type { Snippet } from 'svelte';
	import type { HTMLButtonAttributes } from 'svelte/elements';

	type Props = {
		children: Snippet;
	};

	let { children, ...attributes }: Props & HTMLButtonAttributes = $props();
</script>

<button class="cms-button-menu-entry" role="menuitem" tabindex="-1" {...attributes}>
	{@render children()}
</button>
