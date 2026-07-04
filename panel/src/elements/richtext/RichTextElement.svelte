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

	let active = $derived(field.translate ? locale : ZXX);

	function sync(): LocaleMap<string> {
		return field.translate
			? ensureLocales(value, '', locales?.all ?? [])
			: ensureNeutral(value, '');
	}

	// Synchronous init: the ProseMirror editor reads its content at
	// mount, before effects run; the effect handles later host
	// re-assignments (ensure* helpers are identity-stable).
	let map: LocaleMap<string> = $state(sync());

	$effect(() => {
		map = sync();
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
