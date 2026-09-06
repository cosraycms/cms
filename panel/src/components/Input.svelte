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
	};

	let {
		value = $bindable(),
		label,
		id,
		required = false,
		translate = false,
		locale = ZXX,
		description = '',
	}: Props = $props();

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
				bind:value={value[ZXX]}
			/>
		{/if}
	</div>
	{#if description}
		<div class="cms-field-description">
			{description}
		</div>
	{/if}
</div>
