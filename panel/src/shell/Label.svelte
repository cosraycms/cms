<script lang="ts">
	import type { Snippet } from 'svelte';
	import type { Locale } from '$lib/sys';

	import LocaleTabs from '$shell/LocaleTabs.svelte';

	type Props = {
		of: string;
		translate?: boolean;
		lang?: string | null;
		locales?: Locale[];
		children: Snippet;
	};

	let { of, translate = false, lang = $bindable(null), locales, children }: Props = $props();
</script>

<label for={of} class="cms-field-label">
	<div>{@render children()}</div>
	{#if translate}
		<LocaleTabs bind:lang {locales} />
	{/if}
</label>
