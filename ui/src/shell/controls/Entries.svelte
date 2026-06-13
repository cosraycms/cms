<script lang="ts">
	import type { GenericFieldData, EntriesData, EntryData } from '$types/data';
	import type { Field as FieldType, EntriesField, EntryType } from '$types/fields';

	import { _ } from '$lib/locale';
	import { setDirty } from '$lib/state';
	import { system } from '$lib/sys';
	import { get } from 'svelte/store';
	import { flip } from 'svelte/animate';
	import Field from '$shell/Field.svelte';
	import LabelDiv from '$shell/LabelDiv.svelte';
	import Button from '$shell/Button.svelte';
	import IcoCirclePlus from '$shell/icons/IcoCirclePlus.svelte';
	import Entry from './Entry.svelte';

	type Props = {
		field: EntriesField;
		data: EntriesData;
		node: string;
	};

	let { field, data = $bindable(), node }: Props = $props();

	function createEmptyEntry(entryType: EntryType): EntryData {
		const entry: EntryData = {
			type: entryType.type,
			value: {},
		};

		for (const entryField of entryType.fields) {
			entry.value[entryField.name] = createDefaultValue(entryField);
		}

		return entry;
	}

	function createTranslatableValue(): Record<string, null> {
		const sys = get(system);
		const value: Record<string, null> = {};

		for (const locale of sys.locales) {
			value[locale.id] = null;
		}

		return value;
	}

	function createLocalizedList(): Record<string, []> {
		const sys = get(system);
		const value: Record<string, []> = {};

		for (const locale of sys.locales) {
			value[locale.id] = [];
		}

		return value;
	}

	function createDefaultValue(entryField: FieldType): GenericFieldData {
		const isAsymmetric = entryField.translateMode === 'asymmetric';
		const isSymmetric = entryField.translate === true && !isAsymmetric;
		const codeSyntaxes =
			'syntaxes' in entryField &&
			Array.isArray(entryField.syntaxes) &&
			entryField.syntaxes.length > 0
				? entryField.syntaxes
				: ['plaintext'];

		const typeMap: Record<string, () => GenericFieldData> = {
			'Cosray\\Field\\Text': () => ({
				type: 'text',
				value: isSymmetric ? createTranslatableValue() : '',
			}),
			'Cosray\\Field\\Textarea': () => ({
				type: 'text',
				value: isSymmetric ? createTranslatableValue() : '',
			}),
			'Cosray\\Field\\RichText': () => ({
				type: 'richtext',
				value: isSymmetric ? createTranslatableValue() : '',
			}),
			'Cosray\\Field\\Code': () => ({
				type: 'code',
				syntax: codeSyntaxes[0],
				value: isSymmetric ? createTranslatableValue() : '',
			}),
			'Cosray\\Field\\Checkbox': () => ({ type: 'checkbox', value: false }),
			'Cosray\\Field\\Number': () => ({ type: 'number', value: 0 }),
			'Cosray\\Field\\Date': () => ({ type: 'date', value: '' }),
			'Cosray\\Field\\Time': () => ({ type: 'time', value: '' }),
			'Cosray\\Field\\Image': () => ({
				type: 'image',
				files: isAsymmetric ? createLocalizedList() : [],
			}),
			'Cosray\\Field\\Picture': () => ({
				type: 'picture',
				files: isAsymmetric ? createLocalizedList() : [],
			}),
			'Cosray\\Field\\File': () => ({
				type: 'file',
				files: isAsymmetric ? createLocalizedList() : [],
			}),
			'Cosray\\Field\\Video': () => ({
				type: 'video',
				files: isAsymmetric ? createLocalizedList() : [],
			}),
			'Cosray\\Field\\Blocks': () => ({
				type: 'blocks',
				columns: 12,
				value: isAsymmetric ? createLocalizedList() : [],
			}),
			'Cosray\\Field\\Entries': () => ({ type: 'entries', value: [] }),
			'Cosray\\Field\\Option': () => ({ type: 'option', value: '' }),
			'Cosray\\Field\\Iframe': () => ({ type: 'iframe', value: '' }),
			'Cosray\\Field\\Hidden': () => ({ type: 'hidden', value: '' }),
		};

		const factory = typeMap[entryField.type];
		if (factory) {
			return factory();
		}

		return { type: 'text', value: isSymmetric ? createTranslatableValue() : '' };
	}

	function addEntry(entryType: EntryType) {
		if (!data.value) {
			data.value = [];
		}

		data.value.push(createEmptyEntry(entryType));
		data.value = data.value;
		setDirty();
	}

	function addLabel(entryType: EntryType, first: boolean): string {
		if (field.entryTypes.length === 1) {
			return first ? _('Ersten Eintrag hinzufügen') : _('Eintrag hinzufügen');
		}

		return `${entryType.label} hinzufügen`;
	}
</script>

<Field {field}>
	<LabelDiv translate={false}>
		{field.label}
	</LabelDiv>
	<div class="entries-field">
		{#if data.value && data.value.length > 0}
			<div class="entries-items">
				{#each data.value as entry, index (entry)}
					<div animate:flip={{ duration: 300 }}>
						<Entry
							{field}
							bind:data={data.value}
							bind:entry={data.value[index]}
							{node}
							{index} />
					</div>
				{/each}
			</div>
			<div class="entries-add">
				{#each field.entryTypes as entryType (entryType.type)}
					<Button
						class="secondary"
						onclick={() => addEntry(entryType)}>
						<span class="cms-button-icon">
							<IcoCirclePlus />
						</span>
						{addLabel(entryType, false)}
					</Button>
				{/each}
			</div>
		{:else}
			<div class="entries-empty">
				{#each field.entryTypes as entryType (entryType.type)}
					<Button
						class="secondary"
						onclick={() => addEntry(entryType)}>
						<span class="cms-button-icon">
							<IcoCirclePlus />
						</span>
						{addLabel(entryType, true)}
					</Button>
				{/each}
			</div>
		{/if}
	</div>
</Field>

<style lang="postcss">
	.entries-field {
		margin-top: 0.5rem;
		border: 1px solid var(--cms-color-neutral-300);
		border-radius: 0.375rem;
		background: var(--cms-color-neutral-200);
		padding: 0.75rem;
	}

	.entries-items {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	.entries-add {
		display: flex;
		justify-content: center;
		gap: 0.5rem;
		margin-top: 0.75rem;
		padding-top: 0.75rem;
		border-top: 1px dashed var(--cms-color-neutral-300);
	}

	.entries-empty {
		display: flex;
		justify-content: center;
		gap: 0.5rem;
		padding: 1rem;
	}
</style>
