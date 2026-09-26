<svelte:options customElement={{ tag: 'cosray-code', shadow: 'none' }} />

<script lang="ts">
	import type { LocaleMap, Meta } from '$types/data';

	import { ensureLocales, ensureNeutral } from '$lib/content';
	import { localeTitle, resolveFallback } from '$lib/fallback';
	import { __ } from '$lib/locale';
	import { ZXX } from '$lib/content';
	import CodeEditor from '$components/code/CodeEditor.svelte';
	import { DEFAULT_CODE_SYNTAX, normalizeCodeSyntax } from '$components/code/languages';

	type FieldInfo = {
		name: string;
		required?: boolean;
		immutable?: boolean;
		translate?: boolean;
		syntaxes?: string[];
	};

	type Props = {
		value?: LocaleMap<string>;
		meta?: Meta;
		field?: FieldInfo;
		locale?: string;
		locales?: {
			default: string;
			all: { id: string; title: string; fallback?: string | null }[];
		};
	};

	let { value = {}, meta = {}, field = { name: 'code' }, locale = ZXX, locales }: Props = $props();

	let active = $derived(field.translate ? locale : ZXX);
	let configuredLocales = $derived(locales?.all ?? []);
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
		const syntax = { ...((meta?.syntax as LocaleMap<string> | undefined) ?? {}) };
		const normalized = normalizeCodeSyntax(syntax[ZXX] ?? fallback);
		syntax[ZXX] = syntaxOptions.includes(normalized) ? normalized : fallback;

		return { ...(meta ?? {}), syntax };
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

	let fallback = $derived(
		field.translate && map[active] === '' ? resolveFallback(map, active, configuredLocales) : null,
	);

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

{#if syntaxOptions.length > 1 && metaMap.syntax}
	<div class="cms-code-control-toolbar">
		<label class="cms-code-control-syntax-label" for={`${field.name}-syntax`}>
			{__('code:syntax')}
		</label>
		<select
			class="cms-select cms-code-control-syntax-select"
			id={`${field.name}-syntax`}
			disabled={field.immutable ?? false}
			bind:value={metaMap.syntax[ZXX]}
			onchange={notify}
		>
			{#each syntaxOptions as syntaxOption (syntaxOption)}
				<option value={syntaxOption}>{syntaxOption}</option>
			{/each}
		</select>
	</div>
{/if}

{#if metaMap.syntax}
	{#key active}
		<CodeEditor
			name={field.name}
			required={field.required ?? false}
			readonly={field.immutable ?? false}
			fallback={fallback?.value ?? ''}
			fallbackLabel={fallback
				? __('field:fallback-from', {
						language:
							fallback.locale === ZXX
								? __('field:shared-content')
								: localeTitle(configuredLocales, fallback.locale),
					})
				: ''}
			bind:syntax={metaMap.syntax[ZXX] as string}
			bind:value={map[active]}
			{notify}
		/>
	{/key}
{/if}
