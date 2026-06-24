<script lang="ts">
	import { system } from '$lib/sys';
	import { _ } from '$lib/locale';

	type Props = {
		node: string;
		file: string;
		current: string;
		clickFile: (path: string) => void;
	};

	let { node, file, current = $bindable(), clickFile }: Props = $props();

	const path = $derived(`${$system.prefix}/media/image/node/${node}/${file}`);

	function onclick() {
		clickFile(path);
	}
</script>

<button class="link-image" {onclick} class:active={current && current.endsWith(`/${file}`)}>
	<img src="{path}?resize=fit&w=200&h=200" alt={_('Vorschau')} style="max-height: 90vh" />
</button>

<style>
	@layer panel {
		.link-image {
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: var(--cms-radius);
			border: 1px solid var(--cms-color-neutral-400);
			width: 190px;
			height: 190px;
			padding: 5px;

			&.active {
				border-color: var(--cms-color-success-600);
				background-color: var(--cms-color-success-100);
			}
		}
	}
</style>
