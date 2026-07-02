<script lang="ts">
	import { loadElement } from '$lib/elements';

	type Props = {
		tag: string;
		module: string;
		assign: Record<string, unknown>;
		onchange: (detail: { value?: unknown; meta?: unknown }) => void;
	};

	let { tag, module, assign, onchange }: Props = $props();

	let ready = $state(false);
	let error = $state('');
	// eslint-disable-next-line @typescript-eslint/no-explicit-any
	let host: any = $state();

	$effect(() => {
		ready = false;
		error = '';
		loadElement(module)
			.then(() => (ready = true))
			.catch((err: unknown) => {
				error = `Failed to load control module "${module}": ${String(err)}`;
			});
	});

	$effect(() => {
		if (!host) return;

		for (const [key, value] of Object.entries(assign)) {
			host[key] = value;
		}
	});

	$effect(() => {
		if (!host) return;

		const handler = (event: Event) => onchange((event as CustomEvent).detail ?? {});

		host.addEventListener('cosray-change', handler);

		return () => host.removeEventListener('cosray-change', handler);
	});
</script>

{#if error}
	<div class="cms-control-unknown">{error}</div>
{:else if ready}
	<svelte:element this={tag} bind:this={host}></svelte:element>
{/if}

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
