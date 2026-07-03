<svelte:options customElement={{ tag: 'cosray-blocks', shadow: 'none' }} />

<script lang="ts">
	import type { Block, LocaleMap, Meta } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { ZXX } from '$types/data';
	import { provideNotify } from '../notify';
	import BlocksPanel from './BlocksPanel.svelte';

	type Props = {
		value?: LocaleMap<Block[]>;
		meta?: Meta;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		field?: any;
		node?: string;
		locale?: string;
	};

	let {
		value = {},
		meta = {},
		field = { name: 'blocks' },
		node = '',
		locale = ZXX,
	}: Props = $props();

	let active = $derived(field.translate ? locale : ZXX);

	// The locale-slot default mutates the raw prop object, never the map
	// state — reading the state the effect writes would loop it forever.
	function sync(): LocaleMap<Block[]> {
		const next = value ?? {};
		next[active] ??= [];
		return next;
	}

	// Synchronous init: children mount before effects run; the effect
	// handles later host re-assignments and locale switches.
	let map: LocaleMap<Block[]> = $state(sync());

	$effect(() => {
		map = sync();
	});

	provideNotify(() => {
		$host().dispatchEvent(
			new CustomEvent('cosray-change', {
				detail: { value: map, meta },
				bubbles: true,
				composed: true,
			}),
		);
	});
</script>

{#if map[active]}
	{#key active}
		<BlocksPanel bind:data={map[active]} field={field as BlocksField} {node} />
	{/key}
{/if}
