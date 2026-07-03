<svelte:options customElement={{ tag: 'cosray-entries', shadow: 'none' }} />

<script lang="ts">
	import type { EntryData, LocaleMap } from '$types/data';
	import type { EntriesField } from '$types/fields';

	import { untrack } from 'svelte';

	import { ZXX } from '$types/data';
	import { provideNotify } from '../notify';
	import Entries from './Entries.svelte';

	type Props = {
		value?: LocaleMap<EntryData[]>;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		field?: any;
		node?: string;
	};

	let { value = {}, field = { name: 'entries' }, node = '' }: Props = $props();

	let map: LocaleMap<EntryData[]> = $state({});

	// Untracked: the effect must not depend on the map it writes, or the
	// neutral-slot default would loop it forever.
	$effect(() => {
		map = value ?? {};
		untrack(() => {
			map[ZXX] ??= [];
		});
	});

	provideNotify(() => {
		$host().dispatchEvent(
			new CustomEvent('cosray-change', {
				detail: { value: map },
				bubbles: true,
				composed: true,
			}),
		);
	});
</script>

<Entries
	field={field as EntriesField}
	data={{ type: field.type ?? 'entries', value: map }}
	{node}
/>
