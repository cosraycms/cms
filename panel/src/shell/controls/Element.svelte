<script lang="ts">
	import type { ElementProps } from '$types/controls';
	import type { SimpleField } from '$types/fields';

	import { system, systemLocale } from '$lib/sys';
	import ElementHost from '$shell/ElementHost.svelte';
	import Field from '$shell/Field.svelte';
	import Label from '$shell/Label.svelte';

	type Props = {
		field: SimpleField;
		// eslint-disable-next-line @typescript-eslint/no-explicit-any
		data: any;
		node?: string;
		onchange?: () => void;
	};

	let { field, data = $bindable(), node = '', onchange }: Props = $props();

	let opts = $derived(field.control.props as unknown as ElementProps);
	let lang = $state(systemLocale($system));

	// The element contract: the host assigns these as JS properties and
	// re-assigns them whenever they change (locale follows the tabs);
	// the element reports edits via 'cosray-change' with the full new
	// value (and optionally meta) in the detail.
	let assign = $derived({
		value: data.value,
		meta: data.meta,
		field: { ...field },
		node,
		locale: field.translate ? lang : systemLocale($system),
		locales: {
			default: $system.defaultLocale,
			all: $system.locales.map(({ id, title }) => ({ id, title })),
		},
	});

	function apply(detail: { value?: unknown; meta?: unknown }) {
		data.value = detail.value;

		if (detail.meta !== undefined) {
			data.meta = detail.meta;
		}

		onchange?.();
	}
</script>

<Field {field}>
	<Label of={field.name} translate={field.translate} bind:lang>
		{field.label}
	</Label>
	<div class="cms-field-control">
		<ElementHost tag={opts.tag} module={opts.module} {assign} onchange={apply} />
	</div>
</Field>
