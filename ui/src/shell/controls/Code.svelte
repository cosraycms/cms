<script lang="ts">
	import { ZXX } from '$types/data';
	import type { CodeData } from '$types/data';
	import type { CodeField } from '$types/fields';

	import { ensureLocales, ensureMetaValue, ensureNeutral } from '$lib/content';
	import { _ } from '$lib/locale';
	import { setDirty } from '$lib/state';
	import { system, systemLocale } from '$lib/sys';
	import { DEFAULT_CODE_SYNTAX, normalizeCodeSyntax } from '$shell/code/languages';
	import CodeEditor from '$shell/code/CodeEditor.svelte';
	import Field from '$shell/Field.svelte';
	import LabelDiv from '$shell/LabelDiv.svelte';

	type Props = {
		field: CodeField;
		data: CodeData;
	};

	let { field, data = $bindable() }: Props = $props();
	let lang = $state(systemLocale($system));

	const syntaxOptions = $derived(
		field.syntaxes && field.syntaxes.length > 0 ? field.syntaxes : [DEFAULT_CODE_SYNTAX],
	);

	$effect(() => {
		data.value = field.translate
			? ensureLocales(data.value, '')
			: ensureNeutral(data.value, '');
		ensureMetaValue(data, 'syntax', syntaxOptions[0] ?? DEFAULT_CODE_SYNTAX);
	});

	$effect(() => {
		const syntax = ensureMetaValue(data, 'syntax', syntaxOptions[0] ?? DEFAULT_CODE_SYNTAX);
		const normalized = normalizeCodeSyntax(
			(syntax[ZXX] as string | undefined) ?? syntaxOptions[0] ?? DEFAULT_CODE_SYNTAX,
		);

		if (!syntaxOptions.includes(normalized)) {
			syntax[ZXX] = syntaxOptions[0] ?? DEFAULT_CODE_SYNTAX;
			return;
		}

		if (syntax[ZXX] !== normalized) {
			syntax[ZXX] = normalized;
		}
	});

	function onSyntaxChange() {
		setDirty();
	}
</script>

<Field {field}>
	<LabelDiv
		translate={field.translate}
		bind:lang>
		{field.label}
	</LabelDiv>
	<div class="cms-field-control">
		<div class="cms-code-control-toolbar">
			<label
				class="cms-code-control-syntax-label"
				for={`${field.name}-syntax`}>
				{_('Syntax')}
			</label>
			<select
				class="cms-select cms-code-control-syntax-select"
				id={`${field.name}-syntax`}
				bind:value={data.meta.syntax[ZXX]}
				onchange={onSyntaxChange}>
				{#each syntaxOptions as syntaxOption}
					<option value={syntaxOption}>{syntaxOption}</option>
				{/each}
			</select>
		</div>

		{#if field.translate}
			{#each $system.locales as locale (locale.id)}
				{#if locale.id === lang}
					<CodeEditor
						name={field.name}
						required={field.required}
						bind:syntax={data.meta.syntax[ZXX]}
						bind:value={data.value[locale.id]} />
				{/if}
			{/each}
		{:else}
			<CodeEditor
				name={field.name}
				required={field.required}
				bind:syntax={data.meta.syntax[ZXX]}
				bind:value={data.value[ZXX]} />
		{/if}
	</div>
</Field>
