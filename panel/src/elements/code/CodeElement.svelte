<svelte:options customElement={{ tag: 'cosray-code', shadow: 'none' }} />

<script lang="ts">
	import type { LocaleMap, Meta } from '$types/data';

	import { ensureLocales, ensureNeutral } from '$lib/content';
	import { __ } from '$lib/locale';
	import { ZXX } from '$types/data';
	import CodeEditor from '$shell/code/CodeEditor.svelte';
	import { DEFAULT_CODE_SYNTAX, normalizeCodeSyntax } from '$shell/code/languages';

	type FieldInfo = {
		name: string;
		required?: boolean;
		translate?: boolean;
		syntaxes?: string[];
	};

	type Props = {
		value?: LocaleMap<string>;
		meta?: Meta;
		field?: FieldInfo;
		locale?: string;
		locales?: { default: string; all: { id: string; title: string }[] };
	};

	let { value = {}, meta = {}, field = { name: 'code' }, locale = ZXX, locales }: Props = $props();

	let active = $derived(field.translate ? locale : ZXX);
	let syntaxOptions = $derived(
		field.syntaxes && field.syntaxes.length > 0 ? field.syntaxes : [DEFAULT_CODE_SYNTAX],
	);

	function sync(): LocaleMap<string> {
		return field.translate
			? ensureLocales(value, '', locales?.all ?? [])
			: ensureNeutral(value, '');
	}

	function syncMeta(): Meta {
		const fallback = syntaxOptions[0] ?? DEFAULT_CODE_SYNTAX;
		const result = meta ?? {};
		result.syntax ??= { [ZXX]: fallback };
		const normalized = normalizeCodeSyntax((result.syntax[ZXX] as string | undefined) ?? fallback);
		result.syntax[ZXX] = syntaxOptions.includes(normalized) ? normalized : fallback;
		return result;
	}

	// Synchronous init: CodeMirror reads its content at mount, before
	// effects run; the effects handle later host re-assignments.
	let map: LocaleMap<string> = $state(sync());
	let metaMap: Meta = $state(syncMeta());

	$effect(() => {
		map = sync();
	});

	$effect(() => {
		metaMap = syncMeta();
	});

	function notify() {
		$host().dispatchEvent(
			new CustomEvent('cosray-change', {
				detail: { value: map, meta: metaMap },
				bubbles: true,
				composed: true,
			}),
		);
	}
</script>

<div class="cms-code-control-toolbar">
	<label class="cms-code-control-syntax-label" for={`${field.name}-syntax`}>
		{__('code:syntax')}
	</label>
	{#if metaMap.syntax}
		<select
			class="cms-select cms-code-control-syntax-select"
			id={`${field.name}-syntax`}
			bind:value={metaMap.syntax[ZXX]}
			onchange={notify}
		>
			{#each syntaxOptions as syntaxOption (syntaxOption)}
				<option value={syntaxOption}>{syntaxOption}</option>
			{/each}
		</select>
	{/if}
</div>

{#if metaMap.syntax}
	{#key active}
		<CodeEditor
			name={field.name}
			required={field.required ?? false}
			bind:syntax={metaMap.syntax[ZXX] as string}
			bind:value={map[active]}
			{notify}
		/>
	{/key}
{/if}
