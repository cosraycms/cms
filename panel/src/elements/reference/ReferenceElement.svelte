<svelte:options customElement={{ tag: 'cosray-reference', shadow: 'none' }} />

<script lang="ts">
	import Icon from '$components/Icon.svelte';
	import { onDestroy, onMount, tick } from 'svelte';
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

	const immutable = $derived(field?.immutable === true);
	const ownerType = $derived(typeof field?.ownerType === 'string' ? field.ownerType : '');
	const fieldName = $derived(typeof field?.name === 'string' ? field.name : '');
	const max = $derived(typeof field?.limit?.max === 'number' ? field.limit.max : -1);
	const single = $derived(max === 1);
	const label = $derived(field?.label || fieldName || __('node:search'));
	const id = $props.id();

	let input = $state<HTMLInputElement>();
	let selected = $state<HTMLUListElement>();
	let pageButton = $state<HTMLButtonElement>();
	let items: NodeInfo[] = $state([]);
	let resolving = $state(false);
	let q = $state('');
	let results: NodeInfo[] = $state([]);
	let open = $state(false);
	let loading = $state(false);
	let failed = $state(false);
	let more = $state(false);
	let active = $state(-1);
	let offset = 0;
	let timer: ReturnType<typeof setTimeout> | undefined;
	let request: AbortController | undefined;
	const title = $derived(
		items[0] ? items[0].title || (resolving ? __('common:loading') : items[0].uid) : '',
	);
	const choices = $derived(single ? results : results.filter((result) => !has(result.uid)));
	const activeId = $derived(open && active >= 0 && choices[active] ? `${id}-${active}` : undefined);

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

	async function choose(info: NodeInfo): Promise<void> {
		if (immutable) return;

		if (single) {
			if (!has(info.uid)) {
				items = [info];
				emit();
			}

			input?.focus();
			close();

			return;
		}

		if (has(info.uid) || full()) {
			return;
		}

		items = [...items, info];
		active = -1;
		emit();

		if (full()) {
			q = '';
			close();
		} else if (q !== '') {
			q = '';
			search();
		}

		await tick();

		if (full()) {
			selected?.lastElementChild?.querySelector('button')?.focus();
		} else {
			input?.focus();
		}
	}

	async function remove(uid: string): Promise<void> {
		if (immutable) {
			return;
		}

		items = items.filter((item) => item.uid !== uid);
		active = -1;
		emit();

		if (single) {
			q = '';
			search();
		}

		await tick();
		input?.focus();
	}

	async function query(path: string, params: URLSearchParams, signal: AbortSignal) {
		const response = await fetch(`${panelBase()}${path}?${params.toString()}`, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
			signal,
		});

		if (!response.ok) {
			throw new Error('Could not load reference entries.');
		}

		const data = (await response.json()) as { ok: boolean; nodes: NodeInfo[]; more: boolean };

		if (!data.ok) {
			throw new Error('Could not load reference entries.');
		}

		return data;
	}

	function cancel(): void {
		clearTimeout(timer);
		request?.abort();
		loading = false;
	}

	function close(): void {
		cancel();
		open = false;
		active = -1;

		if (single) q = '';
	}

	async function load(): Promise<void> {
		const controller = new AbortController();
		request = controller;
		loading = true;
		const params = new URLSearchParams({
			type: ownerType,
			field: fieldName,
			q: q.trim(),
			offset: String(offset),
			limit: '30',
		});

		if (node !== '') {
			params.set('node', node);
		}

		try {
			const data = await query('reference/search', params, controller.signal);

			if (controller.signal.aborted) return;

			// Paging counts server rows, including entries already selected in this field.
			offset += data.nodes.length;
			results = [...results, ...data.nodes.filter((n) => !results.some((r) => r.uid === n.uid))];
			more = data.more;
			failed = false;

			if (!more && document.activeElement === pageButton) {
				input?.focus();
			}
		} catch {
			if (controller.signal.aborted) return;

			failed = true;
		} finally {
			if (!controller.signal.aborted) loading = false;
		}
	}

	function search(): void {
		cancel();

		if (immutable || (!single && full()) || ownerType === '') return;

		results = [];
		offset = 0;
		more = false;
		failed = false;
		active = -1;
		open = true;
		loading = true;

		if (q.trim() === '') {
			void load();
		} else {
			timer = setTimeout(() => void load(), 200);
		}
	}

	function show(): void {
		if (!open) search();
	}

	function toggle(): void {
		const wasOpen = open;
		input?.focus();

		if (wasOpen) close();
		else show();
	}

	async function onKeydown(event: KeyboardEvent): Promise<void> {
		if (immutable || event.isComposing) return;

		if (event.key === 'Escape' && open) {
			event.preventDefault();
			event.stopPropagation();
			input?.focus();
			close();

			return;
		}

		if (event.target !== input) return;

		if (event.key === 'Enter') {
			event.preventDefault();
			event.stopPropagation();

			if (!open && single) show();
			else if (open && choices[active]) void choose(choices[active]);

			return;
		}

		if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;

		event.preventDefault();
		event.stopPropagation();
		show();

		if (choices.length === 0) return;

		active =
			event.key === 'ArrowDown'
				? Math.min(active + 1, choices.length - 1)
				: active < 0
					? choices.length - 1
					: Math.max(active - 1, 0);
		await tick();
		document.getElementById(activeId ?? '')?.scrollIntoView({ block: 'nearest' });
	}

	onMount(() => {
		const uids = storedUids();

		if (uids.length === 0) {
			return;
		}

		resolving = true;
		items = uids.map((uid) => ({ uid, title: '', type: '', typeLabel: '' }));

		const controller = new AbortController();
		void query('reference/labels', new URLSearchParams({ uids: uids.join(',') }), controller.signal)
			.then(({ nodes }) => {
				if (controller.signal.aborted) return;

				const map = new Map(nodes.map((n) => [n.uid, n]));
				items = items.map((item) => map.get(item.uid) ?? item);
			})
			.catch(() => {})
			.finally(() => {
				if (!controller.signal.aborted) resolving = false;
			});

		return () => controller.abort();
	});

	onDestroy(cancel);
</script>

<svelte:document
	onpointerdown={(event) => {
		if (open && !$host().contains(event.target as Node)) close();
	}}
/>

<div class="cms-reference">
	{#if single || (!full() && !immutable)}
		<div
			class="cms-reference-search"
			onfocusout={(event) => {
				if (!event.currentTarget.contains(event.relatedTarget as Node | null)) close();
			}}
		>
			<div class="control-wrap">
				<input
					class="cms-input"
					class:single-input={single}
					class:has-selection={single && items.length > 0}
					type="text"
					role={immutable ? undefined : 'combobox'}
					aria-label={label}
					aria-autocomplete={immutable ? undefined : 'list'}
					aria-expanded={immutable ? undefined : open}
					aria-controls={immutable ? undefined : `${id}-results`}
					aria-activedescendant={activeId}
					readonly={immutable}
					autocomplete="off"
					placeholder={single && title ? title : __('reference:placeholder')}
					bind:this={input}
					value={single && !open ? title : q}
					oninput={(event) => {
						q = event.currentTarget.value;
						search();
					}}
					onfocus={show}
					onclick={show}
					onkeydown={onKeydown}
				/>
				{#if single && !immutable}
					<div class="single-tools">
						{#if items[0]}
							<button
								type="button"
								aria-label={__('common:remove')}
								onclick={() => remove(items[0].uid)}
								onkeydown={onKeydown}
							>
								<Icon name="x-lg" />
							</button>
						{/if}
						<button
							type="button"
							tabindex="-1"
							aria-label={open ? __('common:close') : __('common:open')}
							aria-expanded={open}
							aria-controls={`${id}-results`}
							onclick={toggle}
							onkeydown={onKeydown}
						>
							<Icon name="chevron-down" />
						</button>
					</div>
				{/if}
			</div>
			<div class="cms-reference-popup" hidden={!open}>
				{#if q.trim() === ''}
					<div class="cms-reference-note">{__('reference:recent')}</div>
				{/if}
				<ul
					id={`${id}-results`}
					class="cms-reference-results"
					role="listbox"
					aria-label={label}
					aria-busy={loading}
				>
					{#each choices as result, index (result.uid)}
						<li role="presentation">
							<button
								id={`${id}-${index}`}
								type="button"
								role="option"
								tabindex="-1"
								aria-selected={single ? has(result.uid) : index === active}
								class="cms-reference-result"
								class:is-active={index === active}
								onmousedown={(event) => event.preventDefault()}
								onclick={() => choose(result)}
								onkeydown={onKeydown}
							>
								<span class="cms-reference-title">{result.title || result.uid}</span>
								{#if result.typeLabel}
									<span class="cms-reference-type">{result.typeLabel}</span>
								{/if}
								{#if single && has(result.uid)}
									<Icon name="check-lg" />
								{/if}
							</button>
						</li>
					{/each}
				</ul>
				<div class="cms-reference-note" role="status">
					{#if open}
						{#if loading}
							{__('common:loading')}
						{:else if failed}
							{__('reference:failed')}
						{:else if choices.length > 0}
							{__('reference:result-count', { count: choices.length })}
						{:else if more}
							{__('reference:loaded-selected')}
						{:else if q.trim() !== '' && results.length === 0}
							{__('search:no-results')}
						{:else}
							{__('reference:empty')}
						{/if}
					{/if}
				</div>
				{#if more || failed}
					<button
						type="button"
						class="cms-reference-result"
						bind:this={pageButton}
						aria-disabled={loading}
						onkeydown={onKeydown}
						onclick={() => {
							if (!loading) void load();
						}}>{failed ? __('common:retry') : __('common:load-more')}</button
					>
				{/if}
				{#if more}
					<div class="cms-reference-note">{__('reference:refine')}</div>
				{/if}
			</div>
		</div>
	{/if}

	{#if !single && items.length > 0}
		<ul class="cms-reference-list" bind:this={selected}>
			{#each items as item (item.uid)}
				<li class="cms-reference-item">
					<span class="cms-reference-title">
						{item.title || (resolving ? __('common:loading') : item.uid)}
					</span>
					{#if item.typeLabel}
						<span class="cms-reference-type">{item.typeLabel}</span>
					{/if}
					{#if !immutable}
						<button
							type="button"
							class="cms-reference-remove"
							onclick={() => remove(item.uid)}
							aria-label={__('common:remove')}
						>
							<Icon name="x-lg" />
						</button>
					{/if}
				</li>
			{/each}
		</ul>
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
		border: 1px solid var(--cms-color-border);
		border-radius: 0.25rem;
		background: var(--cms-color-surface);
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

	.cms-reference-search,
	.control-wrap {
		position: relative;
	}

	.single-input:not([readonly]) {
		padding-inline-end: calc(var(--cms-space-2-5) + 3.5rem);
	}

	.single-input.has-selection::placeholder {
		color: var(--cms-color-text);
	}

	.single-tools {
		position: absolute;
		inset-block: 1px;
		inset-inline-end: var(--cms-space-2-5);
		display: flex;
		align-items: center;
	}

	.single-tools button {
		display: grid;
		place-items: center;
		width: 1.75rem;
		height: 1.75rem;
		padding: 0;
		border: 0;
		background: transparent;
		color: var(--cms-color-text-muted);
		cursor: pointer;
	}

	.single-tools button:hover {
		color: var(--cms-color-text);
	}

	.cms-reference-popup {
		position: absolute;
		z-index: 20;
		left: 0;
		right: 0;
		margin-top: 0.15rem;
		max-height: 20rem;
		overflow-y: auto;
		border: 1px solid var(--cms-color-border);
		border-radius: 0.25rem;
		background: var(--cms-color-surface);
		box-shadow: var(--cms-shadow-md);
	}

	.cms-reference-results {
		margin: 0;
		padding: 0;
		list-style: none;
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
		background: var(--cms-color-hover);
	}

	.cms-reference-note {
		padding: 0.4rem 0.6rem;
		font-size: 0.85em;
		color: var(--cms-color-text-muted);
	}
</style>
