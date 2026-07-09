<script lang="ts" module>
	export type LocaleText = Record<string, string>;

	export type Meta = {
		alt?: LocaleText;
		title?: LocaleText;
		caption?: LocaleText;
		credit?: string;
		focal?: { x: number; y: number };
		[key: string]: unknown;
	};
</script>

<script lang="ts">
	import type { Locale } from '$lib/sys';
	import { __ } from '$lib/locale';

	type TextKey = 'alt' | 'title' | 'caption';

	type Props = {
		meta: Meta;
		locales: Locale[];
		activeLocale: string;
		isImage: boolean;
	};

	let { meta = $bindable(), locales, activeLocale = $bindable(), isImage }: Props = $props();

	const fields: { key: TextKey; label: string }[] = [
		{ key: 'alt', label: __('image:alt-text-long') },
		{ key: 'title', label: __('common:title') },
		{ key: 'caption', label: __('image:caption') },
	];

	// Alt text describes image content; it is not offered for other kinds.
	const shown = $derived(isImage ? fields : fields.filter((field) => field.key !== 'alt'));

	function text(key: TextKey): string {
		const map = meta[key];

		return (map && typeof map === 'object' ? ((map as LocaleText)[activeLocale] ?? '') : '') || '';
	}

	function setText(key: TextKey, next: string) {
		const map = { ...((meta[key] as LocaleText) ?? {}) };
		map[activeLocale] = next;
		meta = { ...meta, [key]: map };
	}
</script>

<div class="cms-meta-form">
	{#if locales.length > 1}
		<div class="cms-meta-locales" role="tablist">
			{#each locales as locale (locale.id)}
				<button
					type="button"
					role="tab"
					class="cms-meta-locale"
					class:active={activeLocale === locale.id}
					aria-selected={activeLocale === locale.id}
					onclick={() => (activeLocale = locale.id)}
				>
					{locale.id.toUpperCase()}
				</button>
			{/each}
		</div>
	{/if}

	{#each shown as field (field.key)}
		<label class="cms-meta-field">
			<span>{field.label}</span>
			{#if field.key === 'caption'}
				<textarea
					class="cms-input"
					rows="2"
					value={text(field.key)}
					oninput={(event) => setText(field.key, event.currentTarget.value)}
				></textarea>
			{:else}
				<input
					class="cms-input"
					type="text"
					value={text(field.key)}
					oninput={(event) => setText(field.key, event.currentTarget.value)}
				/>
			{/if}
		</label>
	{/each}

	<label class="cms-meta-field">
		<span>{__('image:credit')}</span>
		<input
			class="cms-input"
			type="text"
			value={meta.credit ?? ''}
			oninput={(event) => (meta = { ...meta, credit: event.currentTarget.value })}
		/>
	</label>
</div>

<style>
	@layer panel {
		.cms-meta-form {
			display: flex;
			flex-direction: column;
			gap: var(--space-3);
		}

		.cms-meta-locales {
			display: flex;
			gap: var(--space-1);
		}

		.cms-meta-locale {
			border: 1px solid var(--color-neutral-300);
			border-radius: var(--radius);
			background-color: var(--color-neutral-100);
			padding: var(--space-1) var(--space-2);
			font-size: var(--font-size-xs);
			cursor: pointer;
		}

		.cms-meta-locale.active {
			border-color: var(--color-info);
			color: var(--color-info);
		}

		.cms-meta-field {
			display: flex;
			flex-direction: column;
			gap: var(--space-1);
			font-size: var(--font-size-sm);
		}

		.cms-meta-field > span {
			color: var(--color-neutral-600);
		}
	}
</style>
