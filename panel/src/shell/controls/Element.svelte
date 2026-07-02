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
	import type { ElementProps } from '$types/controls';
	import type { SimpleField } from '$types/fields';

	import { panelBase } from '$lib/runtime';
	import { setDirty } from '$lib/state';
	import { system, systemLocale } from '$lib/sys';
	import Field from '$shell/Field.svelte';
	import Label from '$shell/Label.svelte';

	type Props = {
		field: SimpleField;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		data: any;
	};

	let { field, data = $bindable() }: Props = $props();

	let opts = $derived(field.control.props as unknown as ElementProps);
	let ready = $state(false);
	let error = $state('');
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	let host: any = $state();

	function moduleUrl(module: string): string {
		const base = panelBase().replace(/\/+$/, '');

		return `${base}/vendor/${module}`;
	}

	$effect(() => {
		load(moduleUrl(opts.module))
			.then(() => (ready = true))
			.catch((err: unknown) => {
				error = `Failed to load control module "${opts.module}": ${String(err)}`;
			});
	});

	// The contract with the custom element: the host assigns `value`
	// (the stored value shape, e.g. the locale map), `field`, `locale`
	// and `locales` as JS properties; the element reports edits by
	// dispatching a composed 'cosray-change' event with the full new
	// value in detail.value.
	$effect(() => {
		if (!host) return;

		host.value = data.value;
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
			data.value = (event as CustomEvent).detail?.value;
			setDirty();
		};

		host.addEventListener('cosray-change', onchange);

		return () => host.removeEventListener('cosray-change', onchange);
	});
</script>

<Field {field}>
	<Label of={field.name} translate={field.translate}>
		{field.label}
	</Label>
	<div class="cms-field-control">
		{#if error}
			<div class="cms-control-unknown">{error}</div>
		{:else if ready}
			<svelte:element this={opts.tag} bind:this={host}></svelte:element>
		{/if}
	</div>
</Field>

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
