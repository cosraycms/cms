<svelte:options customElement={{ tag: 'cosray-entries', shadow: 'none' }} />

<script lang="ts">
	import type { EntryData, LocaleMap } from '$types/data';
	import type { EntriesField } from '$types/fields';

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

	// The neutral-slot default mutates the raw prop object, never the map
	// state — reading the state the effect writes would loop it forever.
	function sync(): LocaleMap<EntryData[]> {
		const next = value ?? {};
		next[ZXX] ??= [];
		return next;
	}

	// Synchronous init: children mount before effects run; the effect
	// handles later host re-assignments.
	let map: LocaleMap<EntryData[]> = $state(sync());

	$effect(() => {
		map = sync();
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
