<script lang="ts">
	import type { Field as FieldType } from '$types/fields';

	import { ensureLocales, ensureNeutral } from '$lib/content';
	import { system, systemLocale } from '$lib/sys';
	import { ZXX } from '$types/data';
	import Field from '$shell/Field.svelte';
	import Label from '$shell/Label.svelte';

	type Props = {
		field: FieldType;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		data: any;
		onchange?: () => void;
	};

	let { field, data = $bindable(), onchange = () => {} }: Props = $props();

	// Names rendered as plain <input type=...>. 'hidden' keeps its
	// historical text-input rendering.
	const INPUT_TYPES: Record<string, string> = {
		text: 'text',
		number: 'number',
		date: 'date',
		time: 'time',
		datetime: 'datetime-local',
		hidden: 'text',
	};
	const TRANSLATABLE = ['text', 'textarea', 'iframe'];

	let name = $derived(field.control.name);
	let opts = $derived(
		(field.control.props ?? {}) as {
			step?: number | string;
			min?: number;
			max?: number;
			placeholder?: string;
			display?: string;
		},
	);
	let lang = $state(systemLocale($system));
	let translate = $derived((field.translate ?? false) && TRANSLATABLE.includes(name));
	let options = $derived(
		(field.options ?? []).map((option) =>
			typeof option === 'object' ? option : { value: option, label: option },
		),
	);

	$effect(() => {
		const fallback = name === 'checkbox' ? false : '';
		data.value = translate
			? ensureLocales(data.value, fallback, $system.locales)
			: ensureNeutral(data.value, fallback);
	});
</script>

{#snippet control(key: string)}
	{#if name === 'textarea' || name === 'iframe'}
		<textarea
			class="cms-textarea"
			class:iframe={name === 'iframe'}
			id={field.name}
			name={field.name}
			required={field.required}
			disabled={field.immutable}
			bind:value={data.value[key]}
			oninput={onchange}
		></textarea>
	{:else if name === 'checkbox'}
		<div class="cms-checkbox-input-wrap">
			<input
				id={field.name}
				name={field.name}
				type="checkbox"
				class="cms-checkbox"
				disabled={field.immutable}
				bind:checked={data.value[key]}
				{onchange}
			/>
		</div>
	{:else if name === 'option' && opts.display === 'radio'}
		<div class="cms-radio-group">
			{#each options as option (option.value)}
				<label class="cms-radio">
					<input
						type="radio"
						name={field.name}
						value={option.value}
						required={field.required}
						disabled={field.immutable}
						bind:group={data.value[key]}
						{onchange}
					/>
					{option.label}
				</label>
			{/each}
		</div>
	{:else if name === 'option'}
		<select
			class="cms-select"
			id={field.name}
			name={field.name}
			required={field.required}
			disabled={field.immutable}
			bind:value={data.value[key]}
			{onchange}
		>
			{#each options as option (option.value)}
				<option value={option.value}>{option.label}</option>
			{/each}
		</select>
	{:else}
		<input
			class="cms-input"
			id={field.name}
			name={field.name}
			type={INPUT_TYPES[name] ?? 'text'}
			step={opts.step}
			min={opts.min}
			max={opts.max}
			placeholder={opts.placeholder}
			required={field.required}
			disabled={field.immutable}
			bind:value={data.value[key]}
			oninput={onchange}
		/>
	{/if}
{/snippet}

<Field {field}>
	<Label of={field.name} {translate} bind:lang>
		{field.label}
	</Label>
	<div class="cms-field-control" class:cms-checkbox-wrap={name === 'checkbox'}>
		{#if translate}
			{#each $system.locales as locale (locale.id)}
				{#if locale.id === lang}
					{@render control(locale.id)}
				{/if}
			{/each}
		{:else}
			{@render control(ZXX)}
		{/if}
	</div>
</Field>

<style>
	@layer panel {
		.cms-radio-group {
			display: flex;
			flex-direction: column;
			gap: 0.25rem;
		}

		.cms-radio {
			display: flex;
			align-items: center;
			gap: 0.5rem;
		}
	}
</style>
