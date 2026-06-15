<script lang="ts">
	import type { Locale } from '$lib/sys';
	import { system, localesMap } from '$lib/sys';

	type Props = {
		lang: string | null;
	};

	let { lang = $bindable() }: Props = $props();
	const locales = $derived(
		$system.customLocales.length > 0
			? customLocales($system.customLocales, $system.locales)
			: $system.locales,
	);

	function customLocales(custLocales: string[], locales: Locale[]) {
		const localesObj = localesMap(locales);
		return custLocales.map((lang: string) => localesObj[lang]);
	}
</script>

<span class="locale-tabs">
	{#each locales as locale (locale)}
		<button
			class="locale-tab"
			class:active={locale.id === lang}
			onclick={() => (lang = locale.id)}>
			{locale.id.toUpperCase()}
		</button>
	{/each}
</span>

<style lang="postcss">
	.locale-tab {
		display: inline-block;
		font-size: var(--cms-font-size-sm);
		box-shadow: 0;
		padding: 0 0.5rem;
		font-weight: normal;

		&.active {
			border-radius: var(--cms-radius);
			background-color: var(--cms-color-neutral-200);
			color: var(--cms-color-black);
		}
	}

	.locale-tabs {
		flex-shrink: 0;
	}
</style>
