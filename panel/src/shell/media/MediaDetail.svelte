<script lang="ts">
	import type { Locale } from '$lib/sys';
	import { _ } from '$lib/locale';
	import IcoDocument from '$shell/icons/IcoDocument.svelte';
	import IcoTrash from '$shell/icons/IcoTrash.svelte';
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
			// keep the drawer open on a transport error.
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

	function humanSize(bytes: number | null): string {
		if (bytes === null) {
			return '—';
		}

		const units = ['B', 'KB', 'MB', 'GB'];
		let size = bytes;
		let unit = 0;

		while (size >= 1024 && unit < units.length - 1) {
			size /= 1024;
			unit++;
		}

		return `${unit === 0 ? size : size.toFixed(1)} ${units[unit]}`;
	}

	$effect(() => {
		void loadDetail(uid);
	});
</script>

<div
	class="cms-drawer-overlay"
	role="button"
	tabindex="-1"
	aria-label={_('Schließen')}
	onclick={onClose}
	onkeydown={(event) => event.key === 'Escape' && onClose()}
></div>

<aside class="cms-drawer" aria-label={_('Datei-Details')}>
	{#if loading}
		<div class="cms-drawer-status">{_('Loading ...')}</div>
	{:else if failed || asset === null}
		<div class="cms-drawer-status">{_('Die Datei konnte nicht geladen werden.')}</div>
		<button type="button" class="cms-button" onclick={onClose}>{_('Schließen')}</button>
	{:else}
		<header class="cms-drawer-head">
			<h2 title={asset.filename}>{asset.filename}</h2>
			<button type="button" class="cms-drawer-close" aria-label={_('Schließen')} onclick={onClose}
				>×</button
			>
		</header>

		<div class="cms-drawer-body">
			{#if asset.kind === 'image'}
				<button
					type="button"
					class="cms-drawer-preview focusable"
					title={_('Klicken, um den Bildfokus zu setzen')}
					onclick={setFocal}
				>
					<img src={asset.previewUrl} alt={asset.filename} />
					{#if meta.focal}
						<span
							class="cms-drawer-focal"
							style="left: {meta.focal.x * 100}%; top: {meta.focal.y * 100}%"
						></span>
					{/if}
				</button>
			{:else}
				<div class="cms-drawer-preview">
					<span class="cms-drawer-preview-icon"><IcoDocument /></span>
				</div>
			{/if}

			<dl class="cms-drawer-meta">
				<div>
					<dt>{_('Typ')}</dt>
					<dd>{asset.mime ?? asset.kind}</dd>
				</div>
				{#if asset.width && asset.height}
					<div>
						<dt>{_('Größe')}</dt>
						<dd>{asset.width} × {asset.height} px</dd>
					</div>
				{/if}
				<div>
					<dt>{_('Dateigröße')}</dt>
					<dd>{humanSize(asset.bytes)}</dd>
				</div>
				<div>
					<dt>{_('Original')}</dt>
					<dd><a href={asset.url} target="_blank" rel="noopener">{_('Öffnen')}</a></dd>
				</div>
			</dl>

			{#if isImage}
				<div class="cms-drawer-focal-controls">
					<span>
						{#if meta.focal}
							{_('Fokus')}: {Math.round(meta.focal.x * 100)}% / {Math.round(meta.focal.y * 100)}%
						{:else}
							{_('Kein Bildfokus gesetzt')}
						{/if}
					</span>
					{#if meta.focal}
						<button type="button" class="cms-button" onclick={clearFocal}
							>{_('Fokus entfernen')}</button
						>
					{/if}
				</div>
			{/if}

			<MetaForm bind:meta {locales} bind:activeLocale {isImage} />

			<section class="cms-drawer-usage">
				<h3>{_('Verwendung')}</h3>
				{#if usage.length === 0}
					<p class="cms-drawer-hint">{_('Diese Datei wird nirgendwo verwendet.')}</p>
				{:else}
					<ul>
						{#each usage as owner (owner.ownerType + owner.ownerUid)}
							<li>
								<span class="cms-drawer-usage-title">{owner.title || owner.ownerUid}</span>
								<span class="cms-drawer-usage-kind">
									{owner.nodeType ?? owner.ownerType}
									{#if owner.published === false}· {_('Entwurf')}{/if}
								</span>
							</li>
						{/each}
					</ul>
				{/if}
			</section>
		</div>

		<footer class="cms-drawer-foot">
			<button
				type="button"
				class="cms-button cms-drawer-delete"
				disabled={deleting}
				onclick={remove}
			>
				<IcoTrash />
				{_('Löschen')}
			</button>
			<div class="cms-drawer-foot-right">
				{#if saved}<span class="cms-drawer-saved">{_('Gespeichert')}</span>{/if}
				<button
					type="button"
					class="cms-button cms-button-primary"
					disabled={saving}
					onclick={save}
				>
					{saving ? _('Speichert …') : _('Speichern')}
				</button>
			</div>
		</footer>

		{#if blocked !== null}
			<div class="cms-drawer-blocked">
				<p>{_('Löschen nicht möglich — die Datei wird noch verwendet:')}</p>
				<ul>
					{#each blocked as owner (owner.ownerType + owner.ownerUid)}
						<li>{owner.title || owner.ownerUid} ({owner.nodeType ?? owner.ownerType})</li>
					{/each}
				</ul>
			</div>
		{/if}
	{/if}
</aside>

<style>
	@layer panel {
		.cms-drawer-overlay {
			position: fixed;
			inset: 0;
			background-color: rgba(0, 0, 0, 0.35);
			z-index: 40;
		}

		.cms-drawer {
			position: fixed;
			top: 0;
			right: 0;
			bottom: 0;
			width: min(28rem, 100vw);
			display: flex;
			flex-direction: column;
			background-color: var(--color-neutral-50, #fff);
			border-left: 1px solid var(--color-neutral-300);
			box-shadow: -4px 0 16px rgba(0, 0, 0, 0.12);
			z-index: 41;
		}

		.cms-drawer-head {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: var(--space-2);
			padding: var(--space-4);
			border-bottom: 1px solid var(--color-neutral-200);
		}

		.cms-drawer-head h2 {
			font-size: var(--font-size-md);
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.cms-drawer-close {
			border: 0;
			background: none;
			font-size: 1.5rem;
			line-height: 1;
			cursor: pointer;
			color: var(--color-neutral-500);
		}

		.cms-drawer-body {
			flex: 1 1 auto;
			overflow-y: auto;
			padding: var(--space-4);
			display: flex;
			flex-direction: column;
			gap: var(--space-4);
		}

		.cms-drawer-preview {
			position: relative;
			display: flex;
			align-items: center;
			justify-content: center;
			width: 100%;
			min-height: 8rem;
			max-height: 16rem;
			padding: 0;
			border: 0;
			background-color: var(--color-neutral-100);
			border-radius: var(--radius-md);
			overflow: hidden;
		}

		.cms-drawer-preview.focusable {
			cursor: crosshair;
		}

		.cms-drawer-preview img {
			max-width: 100%;
			max-height: 16rem;
			object-fit: contain;
		}

		.cms-drawer-preview-icon {
			font-size: 3rem;
			color: var(--color-neutral-500);
		}

		.cms-drawer-focal {
			position: absolute;
			width: 0.85rem;
			height: 0.85rem;
			border: 2px solid #fff;
			border-radius: 50%;
			background-color: var(--color-info);
			box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.4);
			transform: translate(-50%, -50%);
			pointer-events: none;
		}

		.cms-drawer-meta {
			display: flex;
			flex-direction: column;
			gap: var(--space-1);
			font-size: var(--font-size-sm);
		}

		.cms-drawer-meta > div {
			display: flex;
			justify-content: space-between;
			gap: var(--space-2);
		}

		.cms-drawer-meta dt {
			color: var(--color-neutral-500);
		}

		.cms-drawer-focal-controls {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: var(--space-2);
			font-size: var(--font-size-sm);
			color: var(--color-neutral-600);
		}

		.cms-drawer-usage h3 {
			font-size: var(--font-size-sm);
			margin-bottom: var(--space-2);
		}

		.cms-drawer-usage ul {
			list-style: none;
			display: flex;
			flex-direction: column;
			gap: var(--space-1);
		}

		.cms-drawer-usage li {
			display: flex;
			justify-content: space-between;
			gap: var(--space-2);
			font-size: var(--font-size-sm);
			padding: var(--space-1) 0;
			border-bottom: 1px solid var(--color-neutral-100);
		}

		.cms-drawer-usage-kind {
			color: var(--color-neutral-500);
			white-space: nowrap;
		}

		.cms-drawer-hint {
			color: var(--color-neutral-500);
			font-size: var(--font-size-sm);
		}

		.cms-drawer-foot {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: var(--space-2);
			padding: var(--space-4);
			border-top: 1px solid var(--color-neutral-200);
		}

		.cms-drawer-foot-right {
			display: flex;
			align-items: center;
			gap: var(--space-3);
		}

		.cms-drawer-delete {
			display: inline-flex;
			align-items: center;
			gap: var(--space-2);
			color: var(--color-danger, #b00020);
		}

		.cms-drawer-saved {
			color: var(--color-success, #178a3a);
			font-size: var(--font-size-sm);
		}

		.cms-drawer-blocked {
			padding: var(--space-3) var(--space-4);
			background-color: var(--color-warning-bg, #fdf3d7);
			border-top: 1px solid var(--color-neutral-200);
			font-size: var(--font-size-sm);
		}

		.cms-drawer-blocked ul {
			margin-top: var(--space-1);
			padding-left: var(--space-4);
			list-style: disc;
		}
	}
</style>
