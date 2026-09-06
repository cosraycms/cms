<script lang="ts">
	import { ZXX, type LocaleMap } from '$types/data';
	import Label from '$components/Label.svelte';

	type Props = {
		value: string | LocaleMap<string>;
		label: string;
		id: string;
		required?: boolean;
		translate?: boolean;
		locale?: string;
		description?: string;
		fallback?: string;
		fallbackLabel?: string;
	};

	let {
		value = $bindable(),
		label,
		id,
		required = false,
		translate = false,
		locale = ZXX,
		description = '',
		fallback = '',
		fallbackLabel = '',
	}: Props = $props();

	let focused = $state(false);
	let localized = $derived(value as LocaleMap<string>);
</script>

<div class="cms-field" class:required>
	<Label of={id}>
		{label}
	</Label>
	<div class="cms-field-control">
		{#if translate}
			<input
				class="cms-input"
				{id}
				name={id}
				type="text"
				{required}
				autocomplete="off"
				placeholder={!focused ? fallback : ''}
				onfocus={() => (focused = true)}
				onblur={() => (focused = false)}
				bind:value={localized[locale]}
			/>
		{:else if typeof value === 'string'}
			<input
				class="cms-input"
				{id}
				name={id}
				type="text"
				{required}
				autocomplete="off"
				placeholder={!focused ? fallback : ''}
				onfocus={() => (focused = true)}
				onblur={() => (focused = false)}
				bind:value
			/>
		{:else}
			<input
				class="cms-input"
				{id}
				name={id}
				type="text"
				{required}
				autocomplete="off"
				placeholder={!focused ? fallback : ''}
				onfocus={() => (focused = true)}
				onblur={() => (focused = false)}
				bind:value={value[ZXX]}
			/>
		{/if}
	</div>
	{#if fallback && fallbackLabel && !focused}
		<div class="cms-fallback-source">{fallbackLabel}</div>
	{/if}
	{#if description}
		<div class="cms-field-description">
			{description}
		</div>
	{/if}
</div>
