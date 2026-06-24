<script lang="ts">
	import { system } from '$lib/sys';

	type Props = {
		node: string;
		file: string;
		current: string;
		clickFile: (path: string) => void;
	};

	let { node, file, current = $bindable(), clickFile }: Props = $props();

	const path = $derived(`${$system.assets}/node/${node}/${file}`);

	function onclick() {
		clickFile(path);
	}
</script>

<div class="cms-modal-link-file-wrap">
	<button
		{onclick}
		class="cms-modal-link-file-button"
		class:active={current && current.endsWith(`/${file}`)}
	>
		{file}
	</button>
</div>

<style>
	@layer panel {
		.cms-modal-link-file-wrap {
			margin-top: var(--space-2);
			padding-right: var(--space-4);
		}

		button.cms-modal-link-file-button {
			display: inline-flex;
			width: 100%;
			align-items: center;
			gap: var(--space-1-5);
			border: 1px solid var(--color-success);
			border-radius: var(--radius-md);
			padding: var(--space-2) var(--space-3);
			font-size: var(--font-size-sm);
			font-weight: 600;
			color: var(--color-success);
			background: transparent;
			box-shadow: var(--shadow-sm);
			cursor: pointer;
		}

		button.cms-modal-link-file-button:focus-visible {
			outline: 2px solid var(--color-success);
			outline-offset: 2px;
		}

		button.active {
			background-color: var(--color-success);
			color: var(--color-white);
		}
	}
</style>
