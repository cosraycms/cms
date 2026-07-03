<svelte:options customElement={{ tag: 'cosray-blocks', shadow: 'none' }} />

<script lang="ts">
	import type { Block, LocaleMap, Meta } from '$types/data';
	import type { BlocksField } from '$types/fields';

	import { untrack } from 'svelte';

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

	let map: LocaleMap<Block[]> = $state({});
	let active = $derived(field.translate ? locale : ZXX);

	// Untracked: the effect must not depend on the map it writes, or the
	// locale-slot default would loop it forever.
	$effect(() => {
		map = value ?? {};
		untrack(() => {
			map[active] ??= [];
		});
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
