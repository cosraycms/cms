<script lang="ts">
	import type { Locale } from '$lib/sys';
	import { humanSize } from '$lib/library';
	import { __ } from '$lib/locale';
	import IcoDocument from '$components/icons/IcoDocument.svelte';
	import IcoTrash from '$components/icons/IcoTrash.svelte';
	import MetaForm, { type Meta } from './MetaForm.svelte';

	type Owner = {
		ownerType: string;
		ownerUid: string;
		title: string;
		nodeType: string | null;
		published: boolean | null;
	};

	type Asset = {
		uid: string;
		filename: string;
		kind: string;
		mime: string | null;
		bytes: number | null;
		width: number | null;
		height: number | null;
		url: string;
		previewUrl: string;
		created: string | null;
		meta: Meta;
	};

	type Props = {
		uid: string;
		prefix: string;
		locales: Locale[];
		defaultLocale: string;
		onClose: () => void;
		onDeleted: () => void;
	};

	let { uid, prefix, locales, defaultLocale, onClose, onDeleted }: Props = $props();

	let asset = $state<Asset | null>(null);
	let usage = $state<Owner[]>([]);
	let meta = $state<Meta>({});
	let activeLocale = $state('');
	let loading = $state(false);
	let failed = $state(false);
	let saving = $state(false);
	let saved = $state(false);
	let deleting = $state(false);
	let blocked = $state<Owner[] | null>(null);

	const isImage = $derived(asset?.kind === 'image');

	async function loadDetail(id: string) {
		loading = true;
		failed = false;
		blocked = null;
		saved = false;

		try {
			const response = await fetch(`${prefix}/media/${id}`, {
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
			});
			const data = (await response.json()) as { ok: boolean; asset: Asset; usage: Owner[] };

			if (data.ok) {
				asset = data.asset;
				usage = data.usage;
				meta = structuredClone(data.asset.meta ?? {});
			} else {
				failed = true;
			}
		} catch {
			failed = true;
		}

		loading = false;
	}

	async function save() {
		saving = true;
		saved = false;

		try {
			const response = await fetch(`${prefix}/media/${uid}`, {
				method: 'PUT',
				credentials: 'same-origin',
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-Requested-With': 'xmlhttprequest',
				},
				body: JSON.stringify({ meta }),
			});
			const data = (await response.json()) as { ok: boolean; meta: Meta };

			if (data.ok) {
				meta = structuredClone(data.meta ?? {});
				saved = true;
			}
		} catch {
			// leave the form as-is; the user can retry.
		}

		saving = false;
	}

	async function remove() {
		deleting = true;
		blocked = null;

		try {
			const response = await fetch(`${prefix}/media/${uid}`, {
				method: 'DELETE',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Requested-With': 'xmlhttprequest' },
			});

			if (response.status === 409) {
				const data = (await response.json()) as { usage: Owner[] };
				blocked = data.usage;
			} else if (response.ok) {
				onDeleted();
			}
		} catch {
			// keep the detail open on a transport error.
		}

		deleting = false;
	}

	function setFocal(event: MouseEvent) {
		// Keyboard activation reports no pointer position; ignore it so a
		// focus-and-Enter does not snap the focal point to the corner.
		if (!isImage || event.detail === 0) {
			return;
		}

		const target = event.currentTarget as HTMLElement;
		const rect = target.getBoundingClientRect();
		const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
		const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));
		meta = { ...meta, focal: { x: round(x), y: round(y) } };
	}

	function clearFocal() {
		const next = { ...meta };
		delete next.focal;
		meta = next;
	}

	function round(n: number): number {
		return Math.round(n * 1000) / 1000;
	}

	$effect(() => {
		// Reset to the default locale tab and (re)load whenever the
		// selected asset changes.
		activeLocale = defaultLocale;
		void loadDetail(uid);
	});
</script>

<div class="cms-detail">
	{#if loading}
		<div class="cms-detail-status">{__('common:loading')}</div>
	{:else if failed || asset === null}
		<div class="cms-detail-status">{__('media:file-load-failed')}</div>
		<button type="button" class="cms-button" onclick={onClose}>{__('common:close')}</button>
	{:else}
		<header class="cms-detail-head">
			<h2 title={asset.filename}>{asset.filename}</h2>
			<button
				type="button"
				class="cms-detail-close"
				aria-label={__('common:close')}
				onclick={onClose}>×</button
			>
		</header>

		<div class="cms-detail-body">
			{#if asset.kind === 'image'}
				<button
					type="button"
					class="cms-detail-preview focusable"
					title={__('image:set-focus-hint')}
					onclick={setFocal}
				>
					<img src={asset.previewUrl} alt={asset.filename} />
					{#if meta.focal}
						<span
							class="cms-detail-focal"
							style="left: {meta.focal.x * 100}%; top: {meta.focal.y * 100}%"
						></span>
					{/if}
				</button>
			{:else}
				<div class="cms-detail-preview">
					<span class="cms-detail-preview-icon"><IcoDocument /></span>
				</div>
			{/if}

			<dl class="cms-detail-meta">
				<div>
					<dt>{__('common:type')}</dt>
					<dd>{asset.mime ?? asset.kind}</dd>
				</div>
				{#if asset.width && asset.height}
					<div>
						<dt>{__('image:size')}</dt>
						<dd>{asset.width} × {asset.height} px</dd>
					</div>
				{/if}
				<div>
					<dt>{__('media:file-size')}</dt>
					<dd>{asset.bytes === null ? '—' : humanSize(asset.bytes)}</dd>
				</div>
				<div>
					<dt>{__('image:original')}</dt>
					<dd><a href={asset.url} target="_blank" rel="noopener">{__('common:open')}</a></dd>
				</div>
			</dl>

			{#if isImage}
				<div class="cms-detail-focal-controls">
					<span>
						{#if meta.focal}
							{__('image:focus')}: {Math.round(meta.focal.x * 100)}% / {Math.round(
								meta.focal.y * 100,
							)}%
						{:else}
							{__('image:no-focus')}
						{/if}
					</span>
					{#if meta.focal}
						<button type="button" class="cms-button" onclick={clearFocal}
							>{__('image:focus-remove')}</button
						>
					{/if}
				</div>
			{/if}

			<MetaForm bind:meta {locales} bind:activeLocale {isImage} />

			<section class="cms-detail-usage">
				<h3>{__('media:usage')}</h3>
				{#if usage.length === 0}
					<p class="cms-detail-hint">{__('media:unused')}</p>
				{:else}
					<ul>
						{#each usage as owner (owner.ownerType + owner.ownerUid)}
							<li>
								<span class="cms-detail-usage-title">{owner.title || owner.ownerUid}</span>
								<span class="cms-detail-usage-kind">
									{owner.nodeType ?? owner.ownerType}
									{#if owner.published === false}· {__('node:draft')}{/if}
								</span>
							</li>
						{/each}
					</ul>
				{/if}
			</section>
		</div>

		<footer class="cms-detail-foot">
			<button
				type="button"
				class="cms-button cms-detail-delete"
				disabled={deleting}
				onclick={remove}
			>
				<IcoTrash />
				{__('common:delete')}
			</button>
			<div class="cms-detail-foot-right">
				{#if saved}<span class="cms-detail-saved">{__('common:saved')}</span>{/if}
				<button
					type="button"
					class="cms-button cms-button-primary"
					disabled={saving}
					onclick={save}
				>
					{saving ? __('common:saving') : __('common:save')}
				</button>
			</div>
		</footer>

		{#if blocked !== null}
			<div class="cms-detail-blocked">
				<p>{__('media:delete-in-use')}</p>
				<ul>
					{#each blocked as owner (owner.ownerType + owner.ownerUid)}
						<li>{owner.title || owner.ownerUid} ({owner.nodeType ?? owner.ownerType})</li>
					{/each}
				</ul>
			</div>
		{/if}
	{/if}
</div>

<style>
	@layer panel {
		.cms-detail {
			flex: 1 1 auto;
			min-height: 0;
			display: flex;
			flex-direction: column;
			background-color: var(--cms-color-surface);
			border: 1px solid var(--cms-color-border-strong);
			border-radius: var(--cms-radius-md);
			overflow: hidden;
		}

		.cms-detail-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: var(--cms-space-2);
			padding: var(--cms-space-4);
			border-bottom: 1px solid var(--cms-color-border);
		}

		.cms-detail-head h2 {
			font-size: var(--cms-font-size-base);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.cms-detail-close {
			border: 0;
			background: none;
			font-size: 1.5rem;
			line-height: 1;
			cursor: pointer;
			color: var(--cms-color-text-subtle);
		}

		.cms-detail-body {
			flex: 1 1 auto;
			min-height: 0;
			overflow-y: auto;
			padding: var(--cms-space-4);
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-4);
		}

		.cms-detail-preview {
			position: relative;
			display: flex;
			align-items: center;
			justify-content: center;
			width: 100%;
			min-height: 8rem;
			max-height: 16rem;
			padding: 0;
			border: 0;
			background-color: var(--cms-color-surface-sunken);
			border-radius: var(--cms-radius-md);
			overflow: hidden;
		}

		.cms-detail-preview.focusable {
			cursor: crosshair;
		}

		.cms-detail-preview img {
			max-width: 100%;
			max-height: 16rem;
			object-fit: contain;
		}

		.cms-detail-preview-icon {
			font-size: 3rem;
			color: var(--cms-color-text-subtle);
		}

		.cms-detail-focal {
			position: absolute;
			width: 0.85rem;
			height: 0.85rem;
			/* Sits on arbitrary media: a white ring inside a dark one, both themes. */
			border: 2px solid #fff;
			border-radius: 50%;
			background-color: var(--cms-color-info);
			box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.4);
			transform: translate(-50%, -50%);
			pointer-events: none;
		}

		.cms-detail-meta {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-1);
			font-size: var(--cms-font-size-sm);
		}

		.cms-detail-meta > div {
			display: flex;
			justify-content: space-between;
			gap: var(--cms-space-2);
		}

		.cms-detail-meta dt {
			color: var(--cms-color-text-subtle);
		}

		.cms-detail-focal-controls {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: var(--cms-space-2);
			font-size: var(--cms-font-size-sm);
			color: var(--cms-color-text-muted);
		}

		.cms-detail-usage h3 {
			font-size: var(--cms-font-size-sm);
			margin-bottom: var(--cms-space-2);
		}

		.cms-detail-usage ul {
			list-style: none;
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-1);
		}

		.cms-detail-usage li {
			display: flex;
			justify-content: space-between;
			gap: var(--cms-space-2);
			font-size: var(--cms-font-size-sm);
			padding: var(--cms-space-1) 0;
			border-bottom: 1px solid var(--cms-color-border-soft);
		}

		.cms-detail-usage-kind {
			color: var(--cms-color-text-subtle);
			white-space: nowrap;
		}

		.cms-detail-hint {
			color: var(--cms-color-text-subtle);
			font-size: var(--cms-font-size-sm);
		}

		.cms-detail-foot {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: var(--cms-space-2);
			padding: var(--cms-space-4);
			border-top: 1px solid var(--cms-color-border);
		}

		.cms-detail-foot-right {
			display: flex;
			align-items: center;
			gap: var(--cms-space-3);
		}

		.cms-detail-delete {
			display: inline-flex;
			align-items: center;
			gap: var(--cms-space-2);
			color: var(--cms-color-danger, #b00020);
		}

		.cms-detail-saved {
			color: var(--cms-color-success, #178a3a);
			font-size: var(--cms-font-size-sm);
		}

		.cms-detail-blocked {
			padding: var(--cms-space-3) var(--cms-space-4);
			background-color: var(--cms-color-warning-surface);
			border-top: 1px solid var(--cms-color-border);
			font-size: var(--cms-font-size-sm);
		}

		.cms-detail-blocked ul {
			margin-top: var(--cms-space-1);
			padding-left: var(--cms-space-4);
			list-style: disc;
		}
	}
</style>
