<svelte:options customElement={{ tag: 'cosray-reference', shadow: 'none' }} />

<script lang="ts">
	import { onMount } from 'svelte';
	import { ZXX, type LocaleMap } from '$types/data';
	import { panelBase } from '$lib/runtime';
	import { __ } from '$lib/locale';

	type RefItem = { uid: string };
	type NodeInfo = { uid: string; title: string; type: string; typeLabel: string };

	type Props = {
		value?: LocaleMap<RefItem[]>;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		field?: any;
		node?: string;
	};

	let { value = {}, field = { name: 'reference' }, node = '' }: Props = $props();

	const ownerType = $derived(typeof field?.ownerType === 'string' ? field.ownerType : '');
	const fieldName = $derived(typeof field?.name === 'string' ? field.name : '');
	const max = $derived(typeof field?.limit?.max === 'number' ? field.limit.max : -1);
	const single = $derived(max === 1);

	let items: NodeInfo[] = $state([]);
	let q = $state('');
	let results: NodeInfo[] = $state([]);
	let open = $state(false);
	let loading = $state(false);
	let active = $state(-1);
	let timer: ReturnType<typeof setTimeout> | undefined;

	function storedUids(): string[] {
		const list = (value ?? {})[ZXX] ?? [];

		return list
			.map((item) => (item && typeof item.uid === 'string' ? item.uid : ''))
			.filter((uid) => uid !== '');
	}

	function full(): boolean {
		return max >= 1 && items.length >= max;
	}

	function has(uid: string): boolean {
		return items.some((item) => item.uid === uid);
	}

	function emit(): void {
		$host().dispatchEvent(
			new CustomEvent('cosray-change', {
				detail: { value: { [ZXX]: items.map((item) => ({ uid: item.uid })) } },
				bubbles: true,
				composed: true,
			}),
		);
	}

	function add(info: NodeInfo): void {
		if (has(info.uid)) {
			return;
		}

		if (single) {
			items = [info];
		} else if (!full()) {
			items = [...items, info];
		} else {
			return;
		}

		q = '';
		results = [];
		open = false;
		active = -1;
		emit();
	}

	function remove(uid: string): void {
		items = items.filter((item) => item.uid !== uid);
		emit();
	}

	async function query(path: string, params: URLSearchParams): Promise<NodeInfo[]> {
		const response = await fetch(`${panelBase()}${path}?${params.toString()}`, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
		});
		const data = (await response.json()) as { ok: boolean; nodes: NodeInfo[] };

		return data.ok ? data.nodes : [];
	}

	async function search(): Promise<void> {
		const term = q.trim();

		if (term === '' || ownerType === '') {
			results = [];
			open = false;

			return;
		}

		loading = true;
		const params = new URLSearchParams({ type: ownerType, field: fieldName, q: term });

		if (node !== '') {
			params.set('node', node);
		}

		try {
			const nodes = await query('reference/search', params);
			results = nodes.filter((n) => !has(n.uid));
			open = true;
			active = -1;
		} catch {
			results = [];
		}

		loading = false;
	}

	function onInput(): void {
		clearTimeout(timer);
		timer = setTimeout(() => void search(), 200);
	}

	function onKeydown(event: KeyboardEvent): void {
		if (event.key === 'Escape') {
			open = false;

			return;
		}

		if (!open || results.length === 0) {
			return;
		}

		if (event.key === 'ArrowDown') {
			event.preventDefault();
			active = (active + 1) % results.length;
		} else if (event.key === 'ArrowUp') {
			event.preventDefault();
			active = (active - 1 + results.length) % results.length;
		} else if (event.key === 'Enter') {
			event.preventDefault();
			const pick = results[active] ?? results[0];

			if (pick) {
				add(pick);
			}
		}
	}

	onMount(() => {
		const uids = storedUids();

		if (uids.length === 0) {
			return;
		}

		// Show rows immediately with the uid as a placeholder label, then
		// swap in resolved titles once the labels endpoint answers.
		items = uids.map((uid) => ({ uid, title: uid, type: '', typeLabel: '' }));

		void query('reference/labels', new URLSearchParams({ uids: uids.join(',') }))
			.then((nodes) => {
				const map = new Map(nodes.map((n) => [n.uid, n]));
				items = uids.map((uid) => map.get(uid) ?? { uid, title: uid, type: '', typeLabel: '' });
			})
			.catch(() => {});
	});
</script>

<div class="cms-reference">
	{#if items.length > 0}
		<ul class="cms-reference-list">
			{#each items as item (item.uid)}
				<li class="cms-reference-item">
					<span class="cms-reference-title">{item.title || item.uid}</span>
					{#if item.typeLabel}
						<span class="cms-reference-type">{item.typeLabel}</span>
					{/if}
					<button
						type="button"
						class="cms-reference-remove"
						onclick={() => remove(item.uid)}
						aria-label={__('common:remove')}
					>
						×
					</button>
				</li>
			{/each}
		</ul>
	{/if}

	{#if !full()}
		<div class="cms-reference-search">
			<input
				class="cms-input"
				type="search"
				placeholder={__('node:search')}
				bind:value={q}
				oninput={onInput}
				onkeydown={onKeydown}
				onblur={() => setTimeout(() => (open = false), 150)}
			/>
			{#if open && results.length > 0}
				<ul class="cms-reference-results">
					{#each results as result, index (result.uid)}
						<li>
							<button
								type="button"
								class="cms-reference-result"
								class:is-active={index === active}
								onmousedown={(event) => {
									event.preventDefault();
									add(result);
								}}
							>
								<span class="cms-reference-title">{result.title || result.uid}</span>
								{#if result.typeLabel}
									<span class="cms-reference-type">{result.typeLabel}</span>
								{/if}
							</button>
						</li>
					{/each}
				</ul>
			{:else if open && q.trim() !== '' && !loading}
				<div class="cms-reference-empty">{__('search:no-results')}</div>
			{/if}
		</div>
	{/if}
</div>

<style>
	.cms-reference {
		display: flex;
		flex-direction: column;
		gap: 0.5rem;
	}

	.cms-reference-list {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
		margin: 0;
		padding: 0;
		list-style: none;
	}

	.cms-reference-item {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		padding: 0.35rem 0.5rem;
		border: 1px solid var(--cms-border, #d0d0d0);
		border-radius: 0.25rem;
		background: var(--cms-surface, #fff);
	}

	.cms-reference-title {
		flex: 1;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	.cms-reference-type {
		font-size: 0.8em;
		opacity: 0.6;
	}

	.cms-reference-remove {
		border: 0;
		background: transparent;
		cursor: pointer;
		font-size: 1.1em;
		line-height: 1;
		padding: 0 0.25rem;
	}

	.cms-reference-search {
		position: relative;
	}

	.cms-reference-results {
		position: absolute;
		z-index: 20;
		left: 0;
		right: 0;
		margin: 0.15rem 0 0;
		padding: 0;
		list-style: none;
		max-height: 16rem;
		overflow-y: auto;
		border: 1px solid var(--cms-border, #d0d0d0);
		border-radius: 0.25rem;
		background: var(--cms-surface, #fff);
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
	}

	.cms-reference-result {
		display: flex;
		align-items: center;
		gap: 0.5rem;
		width: 100%;
		padding: 0.4rem 0.6rem;
		border: 0;
		background: transparent;
		text-align: left;
		cursor: pointer;
	}

	.cms-reference-result.is-active,
	.cms-reference-result:hover {
		background: var(--cms-hover, #eef2ff);
	}

	.cms-reference-empty {
		padding: 0.4rem 0.6rem;
		opacity: 0.6;
	}
</style>
