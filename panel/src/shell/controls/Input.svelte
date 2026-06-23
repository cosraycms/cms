<script lang="ts">
	import { system, systemLocale } from '$lib/sys';
	import { ZXX, type LocaleMap } from '$types/data';
	import { setDirty } from '$lib/state';
	import Label from '$shell/Label.svelte';

	type Props = {
		value: string | LocaleMap<string>;
		label: string;
		id: string;
		required?: boolean;
		translate?: boolean;
		description?: string;
	};

	let {
		value = $bindable(),
		label,
		id,
		required = false,
		translate = false,
		description = '',
	}: Props = $props();

	let lang = $state(systemLocale($system));
	let localized = $derived(value as LocaleMap<string>);

	function oninput() {
		setDirty();
	}
</script>

<div class="cms-field" class:required>
	<Label of={id} {translate} bind:lang>
		{label}
	</Label>
	<div class="cms-field-control">
		{#if translate}
			{#each $system.locales as locale}
				{#if locale.id === lang}
					<input
						class="cms-input"
						{id}
						name={id}
						type="text"
						{required}
						autocomplete="off"
						bind:value={localized[locale.id]}
						{oninput}
					/>
				{/if}
			{/each}
		{:else if typeof value === 'string'}
			<input
				class="cms-input"
				{id}
				name={id}
				type="text"
				{required}
				autocomplete="off"
				bind:value
				{oninput}
			/>
		{:else}
			<input
				class="cms-input"
				{id}
				name={id}
				type="text"
				{required}
				autocomplete="off"
				bind:value={value[ZXX]}
				{oninput}
			/>
		{/if}
	</div>
	{#if description}
		<div class="cms-field-description">
			{description}
		</div>
	{/if}
</div>
