<script lang="ts">
	import { __ } from '$lib/locale';

	// A mirror of the screen's content-language selector for a dialog that
	// holds translated texts: choosing here switches the whole screen. The
	// request travels as an event; the tabs behavior resolves the scope.
	type Props = {
		locales: { id: string; title: string }[];
		locale: string;
	};

	let { locales, locale }: Props = $props();
	const id = $props.id();
	let root: HTMLElement | undefined = $state();

	function choose(id: string) {
		root?.dispatchEvent(
			new CustomEvent('content-locale:select', { bubbles: true, detail: { locale: id } }),
		);
	}
</script>

<div class="cms-content-language">
	{#if locales.length < 4}
		<span class="cms-sub-label" id="{id}-label">{__('editor:content-language')}</span>
		<div class="cms-content-locales" role="group" aria-labelledby="{id}-label" bind:this={root}>
			{#each locales as entry (entry.id)}
				<button
					type="button"
					class="option"
					aria-pressed={entry.id === locale}
					onclick={() => choose(entry.id)}
				>
					{entry.title}
				</button>
			{/each}
		</div>
	{:else}
		<label class="cms-sub-label" for={id}>{__('editor:content-language')}</label>
		<select
			class="cms-select"
			{id}
			value={locale}
			bind:this={root}
			onchange={(event) => choose(event.currentTarget.value)}
		>
			{#each locales as entry (entry.id)}
				<option value={entry.id}>{entry.title}</option>
			{/each}
		</select>
	{/if}
</div>
