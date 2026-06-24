<script lang="ts">
	import { ZXX, type GenericFieldData, type EntriesData, type EntryData } from '$types/data';
	import type { Field as FieldType, EntriesField, EntryType } from '$types/fields';

	import { _ } from '$lib/locale';
	import { setDirty } from '$lib/state';
	import { uid } from '$lib/content';
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
			uid: uid(),
			type: entryType.type,
			fields: {},
		};

		for (const entryField of entryType.fields) {
			entry.fields[entryField.name] = createDefaultValue(entryField);
		}

		return entry;
	}

	function createTranslatableValue(): Record<string, string> {
		const sys = get(system);
		const value: Record<string, string> = {};

		for (const locale of sys.locales) {
			value[locale.id] = '';
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

		const neutral = (value: unknown) => ({ [ZXX]: value });
		const typeMap: Record<string, () => GenericFieldData> = {
			'Cosray\\Field\\Text': () => ({
				type: entryField.type,
				value: isSymmetric ? createTranslatableValue() : neutral(''),
			}),
			'Cosray\\Field\\Textarea': () => ({
				type: entryField.type,
				value: isSymmetric ? createTranslatableValue() : neutral(''),
			}),
			'Cosray\\Field\\RichText': () => ({
				type: entryField.type,
				value: isSymmetric ? createTranslatableValue() : neutral(''),
			}),
			'Cosray\\Field\\Code': () => ({
				type: entryField.type,
				meta: { syntax: neutral(codeSyntaxes[0]) },
				value: isSymmetric ? createTranslatableValue() : neutral(''),
			}),
			'Cosray\\Field\\Checkbox': () => ({ type: entryField.type, value: neutral(false) }),
			'Cosray\\Field\\Number': () => ({ type: entryField.type, value: neutral(0) }),
			'Cosray\\Field\\Date': () => ({ type: entryField.type, value: neutral('') }),
			'Cosray\\Field\\Time': () => ({ type: entryField.type, value: neutral('') }),
			'Cosray\\Field\\Image': () => ({
				type: entryField.type,
				value: isAsymmetric ? createLocalizedList() : neutral([]),
			}),
			'Cosray\\Field\\File': () => ({
				type: entryField.type,
				value: isAsymmetric ? createLocalizedList() : neutral([]),
			}),
			'Cosray\\Field\\Video': () => ({
				type: entryField.type,
				value: isAsymmetric ? createLocalizedList() : neutral([]),
			}),
			'Cosray\\Field\\Blocks': () => ({
				type: entryField.type,
				meta: { columns: neutral(12), minCellWidth: neutral(2) },
				value: isAsymmetric ? createLocalizedList() : neutral([]),
			}),
			'Cosray\\Field\\Entries': () => ({ type: entryField.type, value: neutral([]) }),
			'Cosray\\Field\\Option': () => ({ type: entryField.type, value: neutral('') }),
			'Cosray\\Field\\Iframe': () => ({ type: entryField.type, value: neutral('') }),
			'Cosray\\Field\\Hidden': () => ({ type: entryField.type, value: neutral('') }),
		};

		const factory = typeMap[entryField.type];
		if (factory) {
			return factory();
		}

		return {
			type: entryField.type,
			value: isSymmetric ? createTranslatableValue() : { [ZXX]: '' },
		};
	}

	function addEntry(entryType: EntryType) {
		data.value ??= { [ZXX]: [] };
		data.value[ZXX] ??= [];
		data.value[ZXX].push(createEmptyEntry(entryType));
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
		{#if data.value?.[ZXX] && data.value[ZXX].length > 0}
			<div class="entries-items">
				{#each data.value[ZXX] as entry, index (entry.uid)}
					<div animate:flip={{ duration: 300 }}>
						<Entry
							{field}
							bind:data={data.value[ZXX]}
							bind:entry={data.value[ZXX][index]}
							{node}
							{index}
						/>
					</div>
				{/each}
			</div>
			<div class="entries-add">
				{#each field.entryTypes as entryType (entryType.type)}
					<Button variant="secondary" onclick={() => addEntry(entryType)}>
						<span class="icon">
							<IcoCirclePlus />
						</span>
						{addLabel(entryType, false)}
					</Button>
				{/each}
			</div>
		{:else}
			<div class="entries-empty">
				{#each field.entryTypes as entryType (entryType.type)}
					<Button variant="secondary" onclick={() => addEntry(entryType)}>
						<span class="icon">
							<IcoCirclePlus />
						</span>
						{addLabel(entryType, true)}
					</Button>
				{/each}
			</div>
		{/if}
	</div>
</Field>

<style>
	@layer panel {
		.entries-field {
			margin-top: 0.5rem;
			border: 1px solid var(--color-neutral-300);
			border-radius: 0.375rem;
			background: var(--color-neutral-200);
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
			border-top: 1px dashed var(--color-neutral-300);
		}

		.entries-empty {
			display: flex;
			justify-content: center;
			gap: 0.5rem;
			padding: 1rem;
		}
	}
</style>
