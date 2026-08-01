<script lang="ts">
	import type { Data, EntryData } from '$types/data';
	import type { EntriesField, EntryType, Field, Fieldset } from '$types/fields';

	import { useNotify } from '../notify';
	import Control from '$shell/Control.svelte';
	import EntryControls from './EntryControls.svelte';

	type Props = {
		field: EntriesField;
		data: EntryData[];
		entry: EntryData;
		node: string;
		index: number;
	};

	let { field, data = $bindable(), entry = $bindable(), node, index }: Props = $props();
	const notify = useNotify();

	let collapsed = $state(false);
	let entryType = $derived(field.entryTypes.find((type) => type.type === entry.type));
	let entryFields = $derived(entryType?.fields ?? []);
	let entryFieldsets = $derived(entryType?.fieldsets ?? []);
	let fieldsByName = $derived(
		new Map(entryFields.map((entryField) => [entryField.name, entryField])),
	);
	let fieldsetsByFirst = $derived(
		new Map(
			entryFieldsets
				.filter((fieldset) => fieldset.fields.length > 0)
				.map((fieldset) => [fieldset.fields[0], fieldset]),
		),
	);
	let fieldsetMembers = $derived(new Set(entryFieldsets.flatMap((fieldset) => fieldset.fields)));

	function toggleCollapse() {
		collapsed = !collapsed;
	}

	function getEntryTitle(): string {
		for (const entryField of entryFields) {
			const fieldData = entry.fields[entryField.name] as Data | undefined;
			if (fieldData && 'value' in fieldData) {
				const value = fieldData.value;
				if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
					const record = value as Record<string, unknown>;
					for (const locale of Object.keys(record)) {
						const localeValue = record[locale];
						if (typeof localeValue === 'string' && localeValue.trim()) {
							return localeValue.substring(0, 50) + (localeValue.length > 50 ? '...' : '');
						}
					}
				}
			}
		}
		return `${entryTypeLabel(entryType)} ${index + 1}`;
	}

	function entryTypeLabel(type: EntryType | undefined): string {
		return type?.label ?? 'Entry';
	}

	function widthStyle(width: number | null | undefined): string {
		return width ? `width: calc(${width}% - 0.5rem)` : 'width: 100%';
	}

	function descriptionId(fieldset: Fieldset): string {
		return `entry-${entry.uid}-${fieldset.name}-description`;
	}
</script>

{#snippet renderField(entryField: Field)}
	{#if !entryField.hidden && entry.fields[entryField.name]}
		<div class="entry-field" style={widthStyle(entryField.width)}>
			<Control
				field={entryField}
				{node}
				bind:data={entry.fields[entryField.name]}
				onchange={notify}
			/>
		</div>
	{/if}
{/snippet}

<div class="entry">
	<div class="entry-header">
		<button type="button" class="entry-title" onclick={toggleCollapse}>
			<span class="entry-number">{index + 1}.</span>
			<span class="entry-label">{getEntryTitle()}</span>
		</button>
		<EntryControls bind:data {entry} {index} {collapsed} {toggleCollapse} />
	</div>

	{#if !collapsed}
		<div class="entry-body">
			{#if entryType}
				{#each entryFields as entryField (entryField.name)}
					{@const fieldset = fieldsetsByFirst.get(entryField.name)}
					{#if fieldset}
						<fieldset
							class="entry-fieldset"
							style={widthStyle(fieldset.width)}
							aria-describedby={fieldset.description ? descriptionId(fieldset) : undefined}
						>
							{#if fieldset.label}
								<legend class="entry-fieldset-legend">{fieldset.label}</legend>
							{/if}
							{#if fieldset.description}
								<div id={descriptionId(fieldset)} class="entry-fieldset-description">
									{fieldset.description}
								</div>
							{/if}
							<div class="entry-fieldset-fields">
								{#each fieldset.fields as fieldName (fieldName)}
									{@const fieldsetField = fieldsByName.get(fieldName)}
									{#if fieldsetField}
										{@render renderField(fieldsetField)}
									{/if}
								{/each}
							</div>
						</fieldset>
					{:else if !fieldsetMembers.has(entryField.name)}
						{@render renderField(entryField)}
					{/if}
				{/each}
			{:else}
				<div class="entry-field entry-field-note">Unknown entry type: {entry.type}</div>
			{/if}
		</div>
	{/if}
</div>

<style>
	@layer panel {
		.entry {
			background: white;
			border: 1px solid var(--color-neutral-300);
			border-radius: 0.375rem;
			overflow: hidden;
		}

		.entry-header {
			display: flex;
			flex-direction: row;
			align-items: center;
			justify-content: space-between;
			padding: 0.5rem 0.75rem;
			background: var(--color-neutral-50);
			border-bottom: 1px solid var(--color-neutral-200);
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
				color: var(--color-info);
			}
		}

		.entry-number {
			font-weight: 600;
			color: var(--color-neutral-500);
		}

		.entry-label {
			font-weight: 500;
		}

		.entry-body {
			display: flex;
			flex-wrap: wrap;
			gap: 0.5rem;
			padding: 0.75rem;
			container-type: inline-size;
		}

		.entry-field,
		.entry-fieldset {
			min-width: 200px;
		}

		.entry-fieldset {
			padding: 0.5rem;
			border: 1px solid var(--color-neutral-200);
			border-radius: 0.375rem;
		}

		.entry-fieldset-legend {
			padding: 0 0.25rem;
			font-size: 0.875rem;
			font-weight: 600;
		}

		.entry-fieldset-description {
			padding: 0 0.25rem 0.5rem;
			font-size: 0.8125rem;
			color: var(--color-neutral-500);
		}

		.entry-fieldset-fields {
			display: flex;
			flex-wrap: wrap;
			gap: 0.5rem;
		}

		@container (max-width: 30rem) {
			.entry-fieldset {
				width: 100% !important;
			}
		}

		.entry-field-note {
			padding: 0.75rem;
			color: var(--color-danger);
		}
	}
</style>
