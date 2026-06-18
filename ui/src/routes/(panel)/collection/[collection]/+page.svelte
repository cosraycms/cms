<script lang="ts">
	import { goto } from '$app/navigation';
	import type { Collection } from '$types/data';
	import { _ } from '$lib/locale';
	import req, { base } from '$lib/req';
	import { flattenCollectionNodes } from '$lib/collection-hierarchy';
	import Button from '$shell/Button.svelte';
	import Searchbar from '$shell/Searchbar.svelte';
	import Published from '$shell/Published.svelte';
	import Link from '$shell/Link.svelte';

	type Props = {
		data: Collection & {
			total: number;
			offset: number;
			limit: number;
			q: string;
			sort: string;
			dir: string;
		};
	};

	let { data }: Props = $props();

	let searchTerm = $state(data.q ?? '');
	let expanded = $state<Record<string, boolean>>({});
	let loadingChildren = $state<Record<string, boolean>>({});
	let childrenByParent = $state<Record<string, Collection['nodes']>>({});
	let openChildMenuUid = $state<string | null>(null);
	let hierarchyStateKey = $derived(
		`${data.slug}:${data.q}:${data.sort}:${data.dir}:${data.offset}:${data.limit}`,
	);
	let visibleRows = $derived.by(() =>
		data.showChildren
			? flattenCollectionNodes(data.nodes, childrenByParent, expanded)
			: data.nodes.map(node => ({ node, depth: 0 })),
	);

	$effect(() => {
		searchTerm = data.q ?? '';
	});

	$effect(() => {
		hierarchyStateKey;
		expanded = {};
		loadingChildren = {};
		childrenByParent = {};
		openChildMenuUid = null;
	});

	function fmtDate(d: string) {
		const date = new Date(d);

		return date.toLocaleDateString('de-DE', {
			day: '2-digit',
			month: '2-digit',
			year: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
		});
	}

	function query(params: {
		q?: string;
		offset?: number;
		limit?: number;
		sort?: string;
		dir?: string;
	}) {
		const searchParams = new URLSearchParams();
		const q = (params.q ?? data.q ?? '').trim();
		const offset = Math.max(0, params.offset ?? data.offset ?? 0);
		const limit = Math.max(1, params.limit ?? data.limit ?? 50);
		const sort = (params.sort ?? data.sort ?? '').trim();
		const dir = (params.dir ?? data.dir ?? '').trim();

		if (q !== '') {
			searchParams.set('q', q);
		}

		searchParams.set('offset', String(offset));
		searchParams.set('limit', String(limit));

		if (sort !== '') {
			searchParams.set('sort', sort);
		}

		if (dir !== '') {
			searchParams.set('dir', dir);
		}

		return searchParams.toString();
	}

	function collectionPath(params: {
		q?: string;
		offset?: number;
		limit?: number;
		sort?: string;
		dir?: string;
	}) {
		const qs = query(params);

		if (qs === '') {
			return `${base}collection/${data.slug}`;
		}

		return `${base}collection/${data.slug}?${qs}`;
	}

	function nodePath(uid: string) {
		const qs = query({});

		if (qs === '') {
			return `collection/${data.slug}/${uid}`;
		}

		return `collection/${data.slug}/${uid}?${qs}`;
	}

	function childCreatePath(parentUid: string, type: string) {
		const searchParams = new URLSearchParams(query({}));
		searchParams.set('parent', parentUid);

		return `${base}collection/${data.slug}/create/${type}?${searchParams.toString()}`;
	}

	async function search() {
		await goto(collectionPath({ q: searchTerm, offset: 0 }), {
			invalidateAll: true,
		});
	}

	async function page(offset: number) {
		await goto(collectionPath({ offset }), {
			invalidateAll: true,
		});
	}

	function isExpanded(uid: string) {
		return expanded[uid] === true;
	}

	async function fetchChildren(uid: string) {
		if (loadingChildren[uid] || uid in childrenByParent) {
			return;
		}

		loadingChildren = { ...loadingChildren, [uid]: true };

		const params: Record<string, string> = {
			parent: uid,
			limit: '250',
			offset: '0',
		};

		if (data.sort.trim() !== '') {
			params.sort = data.sort;
		}

		if (data.dir.trim() !== '') {
			params.dir = data.dir;
		}

		const response = await req.get(`collection/${data.slug}`, params);

		if (response?.ok) {
			const payload = response.data as Collection;
			childrenByParent = {
				...childrenByParent,
				[uid]: payload.nodes ?? [],
			};
		} else {
			childrenByParent = {
				...childrenByParent,
				[uid]: [],
			};
		}

		loadingChildren = { ...loadingChildren, [uid]: false };
	}

	async function toggleChildren(event: MouseEvent, uid: string) {
		event.preventDefault();
		event.stopPropagation();
		openChildMenuUid = null;

		if (isExpanded(uid)) {
			expanded = { ...expanded, [uid]: false };

			return;
		}

		if (!(uid in childrenByParent)) {
			await fetchChildren(uid);
		}

		expanded = { ...expanded, [uid]: true };
	}

	function toggleChildMenu(event: MouseEvent, uid: string) {
		event.preventDefault();
		event.stopPropagation();

		openChildMenuUid = openChildMenuUid === uid ? null : uid;
	}

	async function createChild(event: MouseEvent, parentUid: string, type: string) {
		event.preventDefault();
		event.stopPropagation();
		openChildMenuUid = null;

		await goto(childCreatePath(parentUid, type));
	}

	let first = $derived(data.total === 0 ? 0 : data.offset + 1);
	let last = $derived(Math.min(data.total, data.offset + data.nodes.length));
	let hasPrevious = $derived(data.offset > 0);
	let hasNext = $derived(last < data.total);
	let previousOffset = $derived(Math.max(0, data.offset - data.limit));
	let nextOffset = $derived(data.offset + data.limit);
	let createQuery = $derived(query({}));
</script>

<div class="cms-collection-page">
	<Searchbar
		bind:searchTerm
		{search}
		query={createQuery}
		collectionSlug={data.slug}
		blueprints={data.blueprints} />
	<h1 class="cms-collection-title">
		{data.name}
	</h1>
	<div class="cms-collection-content">
		<div class="cms-collection-wrap">
			<div class="cms-collection-ring">
				<div class="cms-collection-scroll">
					<div class="cms-collection-table-wrap">
						<table class="cms-collection-table">
							<thead>
								<tr>
									{#if data.showChildren}
										<th class="cms-tree-col"></th>
									{/if}
									{#if data.showPublished}
										<th class="published"></th>
									{/if}
									{#each data.header as column, i (i)}
										<th scope="col">{column}</th>
									{/each}
								</tr>
							</thead>
							<tbody>
								{#if visibleRows.length === 0}
									<tr>
										<td
											colspan={data.header.length +
												(data.showChildren ? 1 : 0) +
												(data.showPublished ? 1 : 0)}>
											{_('Keine Einträge gefunden')}
										</td>
									</tr>
								{/if}
								{#each visibleRows as row (row.node.uid)}
									{@const node = row.node}
									<tr>
										{#if data.showChildren}
											<td class="cms-tree-cell">
												<div
													class="cms-tree-control"
													style={`--cms-tree-depth:${row.depth}`}>
													{#if node.hasChildren}
														<button
															type="button"
															class="cms-tree-toggle"
															aria-expanded={isExpanded(node.uid)}
															disabled={loadingChildren[node.uid] ===
																true}
															onclick={event =>
																toggleChildren(event, node.uid)}>
															{#if loadingChildren[node.uid] === true}
																…
															{:else if isExpanded(node.uid)}
																▾
															{:else}
																▸
															{/if}
														</button>
													{:else}
														<span class="cms-tree-spacer"></span>
													{/if}
													{#if node.childBlueprints.length > 0}
														<div class="cms-child-create-wrap">
															<button
																type="button"
																class="cms-child-create-toggle"
																aria-expanded={openChildMenuUid ===
																	node.uid}
																onclick={event =>
																	toggleChildMenu(
																		event,
																		node.uid,
																	)}>
																+
															</button>
															{#if openChildMenuUid === node.uid}
																<div class="cms-child-create-menu">
																	{#each node.childBlueprints as blueprint}
																		<button
																			type="button"
																			class="cms-child-create-option"
																			onclick={event =>
																				createChild(
																					event,
																					node.uid,
																					blueprint.slug,
																				)}>
																			{blueprint.name}
																		</button>
																	{/each}
																</div>
															{/if}
														</div>
													{/if}
												</div>
											</td>
										{/if}
										{#if data.showPublished}
											<td class="published cms-published-cell">
												<span class="cms-published-value">
													<Published published={node.published} />
												</span>
											</td>
										{/if}
										{#each node.columns as column, i (i)}
											<td
												class:cms-cell-bold={column.bold}
												class:cms-cell-italic={column.italic}>
												<Link href={nodePath(node.uid)}>
													{#if column.date}
														{fmtDate(column.value.toString())}
													{:else}
														{column.value.toString()}
													{/if}
												</Link>
											</td>
										{/each}
									</tr>
								{/each}
							</tbody>
						</table>
					</div>
				</div>
				<div class="cms-collection-footer">
					<span class="cms-collection-range">
						{first}-{last} / {data.total}
					</span>
					<div class="cms-collection-pagination">
						<Button
							small
							class="secondary"
							disabled={!hasPrevious}
							onclick={() => page(previousOffset)}>
							{_('Zurueck')}
						</Button>
						<Button
							small
							class="secondary"
							disabled={!hasNext}
							onclick={() => page(nextOffset)}>
							{_('Weiter')}
						</Button>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<style lang="postcss">
	.cms-collection-page {
		display: flex;
		height: 100%;
		flex-direction: column;
	}

	.cms-collection-title {
		padding: var(--cms-space-4) var(--cms-space-8);
		font-size: var(--cms-font-size-xl);
		font-weight: 600;
	}

	.cms-collection-content {
		flex: 1 1 auto;
		min-height: 0;
		padding: 0 var(--cms-space-4) var(--cms-space-8);
	}

	.cms-collection-wrap {
		height: 100%;
		min-height: 100%;
		margin: 0 var(--cms-space-8);
	}

	.cms-collection-ring {
		display: flex;
		flex-direction: column;
		height: 100%;
		min-height: 100%;
		margin: 0 calc(var(--cms-space-4) * -1);
		border: 1px solid color-mix(in srgb, var(--cms-color-black) 5%, transparent);
		background-color: var(--cms-color-white);
		box-shadow: var(--cms-shadow);
	}

	.cms-collection-scroll {
		flex: 1 1 auto;
		min-height: 0;
		overflow: auto;
	}

	.cms-collection-table-wrap {
		min-width: 100%;
	}

	.cms-collection-table {
		min-width: 100%;
		border-collapse: separate;
		border-spacing: 0;
		background-color: var(--cms-color-white);
	}

	th,
	td {
		padding-left: var(--cms-space-3);
		padding-right: var(--cms-space-3);
		padding-top: var(--cms-space-4);
		padding-bottom: var(--cms-space-4);
	}

	@media (min-width: var(--cms-breakpoint-sm)) {
		.cms-collection-content {
			padding: 0 var(--cms-space-6);
		}

		.cms-collection-ring {
			margin: 0 calc(var(--cms-space-6) * -1);
		}

		th,
		td {
			padding-left: var(--cms-space-4);
			padding-right: var(--cms-space-4);
		}
	}

	@media (min-width: var(--cms-breakpoint-lg)) {
		.cms-collection-content {
			padding: 0 var(--cms-space-8);
		}

		.cms-collection-ring {
			margin: 0 calc(var(--cms-space-8) * -1);
		}

		th,
		td {
			padding-left: var(--cms-space-6);
			padding-right: var(--cms-space-6);
		}
	}

	th {
		position: sticky;
		top: 0;
		z-index: 10;
		border-top-width: 1px;
		border-bottom-width: 1px;
		border-color: var(--cms-color-neutral-300);
		background-color: color-mix(in srgb, var(--cms-color-neutral-100) 75%, transparent);
		text-align: left;
		font-size: var(--cms-font-size-sm);
		font-weight: 600;
		color: var(--cms-color-neutral-900);
		backdrop-filter: blur(var(--cms-blur-sm));
	}

	td {
		border-bottom: 1px solid var(--cms-color-neutral-200);
		white-space: nowrap;
		font-size: var(--cms-font-size-sm);
		color: var(--cms-color-neutral-900);
	}

	.published {
		padding-right: 0;
	}

	.cms-tree-col,
	.cms-tree-cell {
		padding-left: var(--cms-space-2);
		padding-right: 0;
		width: 1%;
	}

	.cms-tree-control {
		display: flex;
		justify-content: flex-end;
		align-items: center;
		gap: var(--cms-space-1);
		padding-left: calc(var(--cms-tree-depth, 0) * var(--cms-space-5));
		min-width: var(--cms-space-8);
		position: relative;
	}

	.cms-tree-toggle,
	.cms-tree-spacer {
		display: inline-flex;
		justify-content: center;
		align-items: center;
		width: var(--cms-space-6);
		height: var(--cms-space-6);
	}

	.cms-tree-toggle {
		border: 1px solid var(--cms-color-neutral-300);
		border-radius: var(--cms-radius-sm);
		background-color: var(--cms-color-white);
		color: var(--cms-color-neutral-700);
		cursor: pointer;
		padding: 0;
	}

	.cms-tree-toggle:disabled {
		cursor: wait;
		opacity: 0.8;
	}

	.cms-child-create-wrap {
		position: relative;
	}

	.cms-child-create-toggle {
		display: inline-flex;
		justify-content: center;
		align-items: center;
		width: var(--cms-space-6);
		height: var(--cms-space-6);
		border: 1px solid var(--cms-color-neutral-300);
		border-radius: var(--cms-radius-sm);
		background-color: var(--cms-color-white);
		color: var(--cms-color-neutral-700);
		cursor: pointer;
		padding: 0;
		font-size: var(--cms-font-size-sm);
		line-height: 1;
	}

	.cms-child-create-menu {
		position: absolute;
		top: calc(100% + var(--cms-space-1));
		right: 0;
		display: flex;
		flex-direction: column;
		min-width: 11rem;
		padding: var(--cms-space-1);
		border: 1px solid var(--cms-color-neutral-300);
		border-radius: var(--cms-radius-sm);
		background-color: var(--cms-color-white);
		box-shadow: var(--cms-shadow);
		z-index: 20;
	}

	.cms-child-create-option {
		border: none;
		background: transparent;
		text-align: left;
		padding: var(--cms-space-2) var(--cms-space-3);
		border-radius: var(--cms-radius-sm);
		cursor: pointer;
		color: var(--cms-color-neutral-900);
		font-size: var(--cms-font-size-sm);
	}

	.cms-child-create-option:hover {
		background-color: var(--cms-color-neutral-100);
	}

	.cms-published-value {
		display: inline-block;
		padding-bottom: var(--cms-space-1);
	}

	.cms-published-cell {
		text-align: center;
		vertical-align: middle;
	}

	.cms-cell-bold {
		font-weight: 600;
	}

	.cms-cell-italic {
		font-style: italic;
	}

	.cms-collection-footer {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: var(--cms-space-4);
		padding: var(--cms-space-4);
		background-color: var(--cms-color-white);
		border-top: 1px solid var(--cms-color-neutral-200);
		flex-shrink: 0;
	}

	.cms-collection-range {
		font-size: var(--cms-font-size-sm);
		color: var(--cms-color-neutral-700);
	}

	.cms-collection-pagination {
		display: flex;
		gap: var(--cms-space-2);
	}

	tr:hover {
		td,
		:global(td a) {
			background-color: var(--cms-color-success-100);
		}
	}
</style>
