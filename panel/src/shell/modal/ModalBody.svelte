<script lang="ts">
	import type { Snippet } from 'svelte';

	let { children, class: classes = '' }: { children: Snippet; class?: string } = $props();
</script>

<div class="modal-body cms-modal-body {classes}">
	{@render children()}
</div>
