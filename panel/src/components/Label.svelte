<script lang="ts">
	import type { Snippet } from 'svelte';
	import type { Locale } from '$lib/sys';
	import { __ } from '$lib/locale';

	import LocaleTabs from '$components/LocaleTabs.svelte';

	type Props = {
		of: string;
		required?: boolean;
		immutable?: boolean;
		translate?: boolean;
		lang?: string | null;
		locales?: Locale[];
		children: Snippet;
	};

	let {
		of,
		required = false,
		immutable = false,
		translate = false,
		lang = $bindable(null),
		locales,
		children,
	}: Props = $props();
</script>

<label for={of} class="label">
	<div>
		{@render children()}
		<!-- A field the editor cannot change is never also required of them. -->
		{#if immutable}
			<span class="state">({__('field:readonly')})</span>
		{:else if required}
			<span class="requirement">({__('field:required')})</span>
		{/if}
	</div>
	{#if translate}
		<LocaleTabs bind:lang {locales} />
	{/if}
</label>
