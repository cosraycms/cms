<script lang="ts">
	import type { Snippet } from 'svelte';

	let { children }: { children: Snippet } = $props();
</script>

<header class="modal-header">
	<h2 class="modal-title" data-dialog-title>{@render children()}</h2>
</header>
