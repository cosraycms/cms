<script lang="ts">
	import type { Toast } from '$lib/toast';

	type Props = {
		toast: Toast;
	};

	let { toast }: Props = $props();
</script>

<div class="cms-toast-content">
	{#if toast.title}
		<h3 class="cms-toast-title">{toast.title}</h3>
	{/if}
	{#if toast.message}
		<p>{toast.message}</p>
	{/if}
</div>

<style lang="postcss">
	.cms-toast-content {
		text-align: left;
		color: var(--cms-color-white);
	}

	.cms-toast-title {
		margin-top: var(--cms-space-3);
		margin-bottom: var(--cms-space-1);
		font-weight: 600;
	}
</style>
