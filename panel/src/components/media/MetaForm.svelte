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
	import { __ } from '$lib/locale';

	type TextKey = 'alt' | 'title' | 'caption';

	type Props = {
		meta: Meta;
		// The language whose texts the form edits: the screen's selection.
		locale: string;
		isImage: boolean;
	};

	let { meta = $bindable(), locale, isImage }: Props = $props();

	const fields: { key: TextKey; label: string }[] = [
		{ key: 'alt', label: __('image:alt-text-long') },
		{ key: 'title', label: __('common:title') },
		{ key: 'caption', label: __('image:caption') },
	];

	// Alt text describes image content; it is not offered for other kinds.
	const shown = $derived(isImage ? fields : fields.filter((field) => field.key !== 'alt'));

	function text(key: TextKey): string {
		const map = meta[key];

		return (map && typeof map === 'object' ? ((map as LocaleText)[locale] ?? '') : '') || '';
	}

	function setText(key: TextKey, next: string) {
		const map = { ...((meta[key] as LocaleText) ?? {}) };
		map[locale] = next;
		meta = { ...meta, [key]: map };
	}
</script>

<div class="cms-meta-form">
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
			gap: var(--cms-space-3);
		}

		.cms-meta-field {
			display: flex;
			flex-direction: column;
			gap: var(--cms-space-1);
			font-size: var(--cms-font-size-sm);
		}

		.cms-meta-field > span {
			color: var(--cms-color-text-label);
		}
	}
</style>
