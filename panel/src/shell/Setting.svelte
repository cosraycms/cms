<script lang="ts">
	import type { Snippet } from 'svelte';

	type Props = {
		required?: boolean;
		children: Snippet;
	};

	let { required = false, children }: Props = $props();
</script>

<div class="setting" class:required>
	{@render children()}
</div>
