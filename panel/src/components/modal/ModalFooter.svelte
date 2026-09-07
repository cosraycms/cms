<script lang="ts">
	import type { Snippet } from 'svelte';

	let { children, class: classes = '' }: { children: Snippet; class?: string } = $props();
</script>

<footer class="modal-footer {classes}">
	{@render children()}
</footer>
