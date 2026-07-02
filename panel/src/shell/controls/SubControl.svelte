<script lang="ts">
	import type { ControlDescriptor } from '$types/controls';

	import { setDirty } from '$lib/state';

	type Props = {
		descriptor: ControlDescriptor;
		id: string;
		label: string;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		value: any;
	};

	let { descriptor, id, label, value = $bindable() }: Props = $props();

	const INPUT_TYPES: Record<string, string> = {
		text: 'text',
		number: 'number',
		date: 'date',
		time: 'time',
		datetime: 'datetime-local',
		hidden: 'hidden',
	};

	let opts = $derived(descriptor.props ?? {});
	let options = $derived(
		((opts.options as Array<string | { value: string; label: string }>) ?? []).map((option) =>
			typeof option === 'object' ? option : { value: option, label: option },
		),
	);

	function onchange() {
		setDirty();
	}
</script>

{#if descriptor.name in INPUT_TYPES}
	<label class="cms-sub-label" for={id}>{label}</label>
	<input
		class="cms-input"
		{id}
		type={INPUT_TYPES[descriptor.name]}
		step={opts.step as number | string | undefined}
		min={opts.min as number | undefined}
		max={opts.max as number | undefined}
		placeholder={opts.placeholder as string | undefined}
		bind:value
		oninput={onchange}
	/>
{:else if descriptor.name === 'textarea'}
	<label class="cms-sub-label" for={id}>{label}</label>
	<textarea class="cms-input" {id} bind:value oninput={onchange}></textarea>
{:else if descriptor.name === 'checkbox'}
	<label class="cms-sub-label cms-sub-checkbox">
		<input type="checkbox" {id} bind:checked={value} {onchange} />
		{label}
	</label>
{:else if descriptor.name === 'option' && opts.display === 'radio'}
	<span class="cms-sub-label">{label}</span>
	{#each options as option}
		<label class="cms-sub-checkbox">
			<input type="radio" name={id} value={option.value} bind:group={value} {onchange} />
			{option.label}
		</label>
	{/each}
{:else if descriptor.name === 'option'}
	<label class="cms-sub-label" for={id}>{label}</label>
	<select class="cms-select" {id} bind:value {onchange}>
		{#each options as option}
			<option value={option.value}>{option.label}</option>
		{/each}
	</select>
{:else}
	<div class="cms-control-unknown">
		Control "{descriptor.name}" is not supported inside group/repeater
	</div>
{/if}

<style>
	@layer panel {
		.cms-sub-label {
			display: block;
			font-size: 0.8125rem;
			font-weight: 500;
			margin-bottom: 0.25rem;
		}

		.cms-sub-checkbox {
			display: flex;
			align-items: center;
			gap: 0.5rem;
			font-weight: 400;
		}

		.cms-control-unknown {
			padding: 0.5rem;
			border: 1px dashed var(--color-danger);
			border-radius: 0.375rem;
			color: var(--color-danger);
			font-size: 0.8125rem;
		}
	}
</style>
