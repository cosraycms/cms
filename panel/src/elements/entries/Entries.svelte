<script lang="ts">
	import { ZXX, type EntriesData, type EntryData, type GenericFieldData } from '$types/data';
	import type { EntriesField, EntryType } from '$types/fields';

	import { _ } from '$lib/locale';
	import { useNotify } from '../notify';
	import { uid } from '$lib/content';
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
	const notify = useNotify();

	function createEmptyEntry(entryType: EntryType): EntryData {
		return {
			uid: uid(),
			type: entryType.type,
			fields: structuredClone(entryType.init) as Record<string, GenericFieldData>,
		};
	}

	function addEntry(entryType: EntryType) {
		data.value ??= { [ZXX]: [] };
		data.value[ZXX] ??= [];
		data.value[ZXX].push(createEmptyEntry(entryType));
		data.value = data.value;
		notify();
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
