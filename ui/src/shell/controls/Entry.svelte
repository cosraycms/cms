<script lang="ts">
	import type { Data, EntryData } from '$types/data';
	import type { EntriesField, Field } from '$types/fields';
	import type { Component } from 'svelte';

	import controls from '$lib/controls';
	import EntryControls from './EntryControls.svelte';

	type Props = {
		field: EntriesField;
		data: EntryData[];
		entry: EntryData;
		node: string;
		index: number;
	};

	let { field, data = $bindable(), entry = $bindable(), node, index }: Props = $props();

	let collapsed = $state(false);

	function toggleCollapse() {
		collapsed = !collapsed;
	}

	function getEntryTitle(): string {
		for (const entryField of field.entryFields) {
			const fieldData = entry[entryField.name] as Data | undefined;
			if (fieldData && 'value' in fieldData) {
				const value = fieldData.value;
				if (typeof value === 'string' && value.trim()) {
					return value.substring(0, 50) + (value.length > 50 ? '...' : '');
				}
				if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
					const record = value as Record<string, unknown>;
					for (const locale of Object.keys(record)) {
						const localeValue = record[locale];
						if (typeof localeValue === 'string' && localeValue.trim()) {
							return (
								localeValue.substring(0, 50) +
								(localeValue.length > 50 ? '...' : '')
							);
						}
					}
				}
			}
		}
		return `Entry ${index + 1}`;
	}

	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	type AnyComponent = Component<any, any, any>;
</script>

<div class="entry">
	<div class="entry-header">
		<button
			type="button"
			class="entry-title"
			onclick={toggleCollapse}>
			<span class="entry-number">{index + 1}.</span>
			<span class="entry-label">{getEntryTitle()}</span>
		</button>
		<EntryControls
			bind:data
			{entry}
			{index}
			{collapsed}
			{toggleCollapse} />
	</div>

	{#if !collapsed}
		<div class="entry-body">
			{#each field.entryFields as entryField (entryField.name)}
				{#if !entryField.hidden && entry[entryField.name]}
					{@const SvelteComponent = controls[entryField.type as keyof typeof controls] as
						| AnyComponent
						| undefined}
					{@const widthStyle = entryField.width
						? `width: calc(${entryField.width}% - 0.5rem)`
						: 'width: 100%'}
					{#if SvelteComponent}
						<div
							class="entry-field"
							style={widthStyle}>
							<SvelteComponent
								field={entryField}
								{node}
								bind:data={entry[entryField.name]} />
						</div>
					{:else}
						<div
							class="entry-field entry-field-note"
							style={widthStyle}>
							Unknown field type: {entryField.type}
						</div>
					{/if}
				{/if}
			{/each}
		</div>
	{/if}
</div>

<style lang="postcss">
	.entry {
		background: white;
		border: 1px solid var(--cms-color-neutral-300);
		border-radius: 0.375rem;
		overflow: hidden;
	}

	.entry-header {
		display: flex;
		flex-direction: row;
		align-items: center;
		justify-content: space-between;
		padding: 0.5rem 0.75rem;
		background: var(--cms-color-neutral-50);
		border-bottom: 1px solid var(--cms-color-neutral-200);
	}

	.entry-title {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 0.5rem;
		flex-grow: 1;
		text-align: left;
		font-size: 0.875rem;
		cursor: pointer;

		&:hover {
			color: var(--cms-color-info-700);
		}
	}

	.entry-number {
		font-weight: 600;
		color: var(--cms-color-neutral-500);
	}

	.entry-label {
		color: var(--cms-color-neutral-700);
	}

	.entry-body {
		padding: 1rem;
		display: flex;
		flex-wrap: wrap;
		gap: 1rem;
	}

	.entry-field {
		flex-shrink: 0;
		min-width: 0;
	}

	.entry-field-note {
		color: var(--cms-color-neutral-500);
	}
</style>
