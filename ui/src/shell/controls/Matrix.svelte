<script lang="ts">
	import type { GenericFieldData, MatrixData, MatrixItemData } from '$types/data';
	import type { Field as FieldType, MatrixField } from '$types/fields';

	import { _ } from '$lib/locale';
	import { setDirty } from '$lib/state';
	import { system } from '$lib/sys';
	import { get } from 'svelte/store';
	import { flip } from 'svelte/animate';
	import Field from '$shell/Field.svelte';
	import LabelDiv from '$shell/LabelDiv.svelte';
	import Button from '$shell/Button.svelte';
	import IcoCirclePlus from '$shell/icons/IcoCirclePlus.svelte';
	import MatrixItem from './MatrixItem.svelte';

	type Props = {
		field: MatrixField;
		data: MatrixData;
		node: string;
	};

	let { field, data = $bindable(), node }: Props = $props();

	function createEmptyItem(): MatrixItemData {
		const item: MatrixItemData = {};

		for (const subfield of field.subfields) {
			// Create default structure for each subfield based on its type
			item[subfield.name] = createDefaultValue(subfield);
		}

		return item;
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

	function createDefaultValue(subfield: FieldType): GenericFieldData {
		const isAsymmetric = subfield.translateMode === 'asymmetric';
		const isSymmetric = subfield.translate === true && !isAsymmetric;
		const codeSyntaxes =
			'syntaxes' in subfield &&
			Array.isArray(subfield.syntaxes) &&
			subfield.syntaxes.length > 0
				? subfield.syntaxes
				: ['plaintext'];

		// Return appropriate default structure based on field type
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
			'Cosray\\Field\\Option': () => ({ type: 'option', value: '' }),
			'Cosray\\Field\\Iframe': () => ({ type: 'iframe', value: '' }),
			'Cosray\\Field\\Hidden': () => ({ type: 'hidden', value: '' }),
		};

		const factory = typeMap[subfield.type];
		if (factory) {
			return factory();
		}

		// Default fallback for unknown types
		return { type: 'text', value: isSymmetric ? createTranslatableValue() : '' };
	}

	function addItem() {
		if (!data.value) {
			data.value = [];
		}

		data.value.push(createEmptyItem());
		data.value = data.value;
		setDirty();
	}
</script>

<Field {field}>
	<LabelDiv translate={false}>
		{field.label}
	</LabelDiv>
	<div class="matrix-field">
		{#if data.value && data.value.length > 0}
			<div class="matrix-items">
				{#each data.value as item, index (item)}
					<div animate:flip={{ duration: 300 }}>
						<MatrixItem
							{field}
							bind:data={data.value}
							bind:item={data.value[index]}
							{node}
							{index} />
					</div>
				{/each}
			</div>
			<div class="matrix-add">
				<Button
					class="secondary"
					onclick={addItem}>
					<span class="cms-button-icon">
						<IcoCirclePlus />
					</span>
					{_('Eintrag hinzufügen')}
				</Button>
			</div>
		{:else}
			<div class="matrix-empty">
				<Button
					class="secondary"
					onclick={addItem}>
					<span class="cms-button-icon">
						<IcoCirclePlus />
					</span>
					{_('Ersten Eintrag hinzufügen')}
				</Button>
			</div>
		{/if}
	</div>
</Field>

<style lang="postcss">
	.matrix-field {
		margin-top: 0.5rem;
		border: 1px solid var(--cms-color-neutral-300);
		border-radius: 0.375rem;
		background: var(--cms-color-neutral-200);
		padding: 0.75rem;
	}

	.matrix-items {
		display: flex;
		flex-direction: column;
		gap: 0.75rem;
	}

	.matrix-add {
		display: flex;
		justify-content: center;
		margin-top: 0.75rem;
		padding-top: 0.75rem;
		border-top: 1px dashed var(--cms-color-neutral-300);
	}

	.matrix-empty {
		display: flex;
		justify-content: center;
		padding: 1rem;
	}
</style>
