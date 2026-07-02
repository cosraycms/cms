<script lang="ts" module>
	const modules = new Map<string, Promise<unknown>>();

	function load(url: string): Promise<unknown> {
		let promise = modules.get(url);

		if (!promise) {
			promise = import(/* @vite-ignore */ url);
			modules.set(url, promise);
		}

		return promise;
	}
</script>

<script lang="ts">
	import type { Snippet } from 'svelte';
	import type { BlockCustom } from '$types/data';
	import type { ElementProps } from '$types/controls';
	import type { BlocksField } from '$types/fields';

	import { panelBase } from '$lib/runtime';
	import { setDirty } from '$lib/state';
	import { system, systemLocale } from '$lib/sys';

	type Props = {
		field: BlocksField;
		item: BlockCustom;
		index: number;
		children: Snippet<[{ edit: () => void }]>;
	};

	let { field, item = $bindable(), index, children }: Props = $props();

	let meta = $derived(field.blockTypes.find((type) => type.id === item.type));
	let opts = $derived(meta?.control.props as unknown as ElementProps | undefined);
	let ready = $state(false);
	let error = $state('');
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	let host: any = $state();

	$effect(() => {
		if (!opts) {
			error = `Unknown block type "${item.type}"`;

			return;
		}

		const base = panelBase().replace(/\/+$/, '');
		load(`${base}/vendor/${opts.module}`)
			.then(() => (ready = true))
			.catch((err: unknown) => {
				error = `Failed to load block module "${opts?.module}": ${String(err)}`;
			});
	});

	$effect(() => {
		if (!host) return;

		host.value = item.value;
		host.block = { type: item.type, index };
		host.field = { ...field };
		host.locale = systemLocale($system);
		host.locales = {
			default: $system.defaultLocale,
			all: $system.locales,
		};
	});

	$effect(() => {
		if (!host) return;

		const onchange = (event: Event) => {
			item.value = (event as CustomEvent).detail?.value;
			setDirty();
		};

		host.addEventListener('cosray-change', onchange);

		return () => host.removeEventListener('cosray-change', onchange);
	});
</script>

<div class="block-cell-header">
	{@render children({ edit: () => {} })}
</div>
<div class="block-cell-body">
	{#if error}
		<div class="cms-control-unknown">{error}</div>
	{:else if ready && opts}
		<svelte:element this={opts.tag} bind:this={host}></svelte:element>
	{/if}
</div>

<style>
	@layer panel {
		.cms-control-unknown {
			padding: 0.75rem;
			border: 1px dashed var(--color-danger);
			border-radius: 0.375rem;
			color: var(--color-danger);
			font-size: 0.875rem;
		}
	}
</style>
