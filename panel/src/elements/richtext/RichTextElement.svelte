<svelte:options customElement={{ tag: 'cosray-richtext', shadow: 'none' }} />

<script lang="ts">
	import type { LocaleMap } from '$types/data';

	import { ensureLocales, ensureNeutral } from '$lib/content';
	import { ZXX } from '$types/data';
	import RichTextEditor from '$shell/richtext/RichTextEditor.svelte';

	type FieldInfo = {
		name: string;
		required?: boolean;
		translate?: boolean;
	};

	type Props = {
		value?: LocaleMap<string>;
		field?: FieldInfo;
		locale?: string;
		locales?: { default: string; all: { id: string; title: string }[] };
	};

	let { value = {}, field = { name: 'richtext' }, locale = ZXX, locales }: Props = $props();

	let map: LocaleMap<string> = $state({});
	let active = $derived(field.translate ? locale : ZXX);

	// Sync from host property assignments; the element owns the map
	// between assignments.
	$effect(() => {
		map = field.translate ? ensureLocales(value, '', locales?.all ?? []) : ensureNeutral(value, '');
	});

	function notify() {
		$host().dispatchEvent(
			new CustomEvent('cosray-change', {
				detail: { value: map },
				bubbles: true,
				composed: true,
			}),
		);
	}
</script>

{#key active}
	<RichTextEditor
		name={field.name}
		required={field.required ?? false}
		bind:value={map[active]}
		{notify}
	/>
{/key}
