<script lang="ts">
	import type { Locale } from '$lib/sys';
	import { system, localesMap } from '$lib/sys';

	type Props = {
		lang: string | null;
		// Explicit locale list for use inside element bundles where the
		// island's system store is not available.
		locales?: Locale[];
	};

	let { lang = $bindable(), locales: given }: Props = $props();
	const locales = $derived(
		given ??
			($system.customLocales.length > 0
				? customLocales($system.customLocales, $system.locales)
				: $system.locales),
	);

	function customLocales(custLocales: string[], locales: Locale[]) {
		const localesObj = localesMap(locales);
		return custLocales.map((lang: string) => localesObj[lang]);
	}
</script>

<span class="locale-tabs">
	{#each locales as locale (locale)}
		<button class="locale-tab" class:active={locale.id === lang} onclick={() => (lang = locale.id)}>
			{locale.id.toUpperCase()}
		</button>
	{/each}
</span>

<style>
	@layer panel {
		.locale-tab {
			display: inline-block;
			font-size: var(--font-size-sm);
			box-shadow: 0;
			padding: 0 0.5rem;
			font-weight: normal;

			&.active {
				border-radius: var(--radius);
				background-color: var(--color-neutral-200);
				color: var(--color-black);
			}
		}

		.locale-tabs {
			flex-shrink: 0;
		}
	}
</style>
