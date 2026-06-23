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

<style lang="postcss">
	.cms-modal-link-file-wrap {
		margin-top: var(--cms-space-2);
		padding-right: var(--cms-space-4);
	}

	button.cms-modal-link-file-button {
		display: inline-flex;
		width: 100%;
		align-items: center;
		gap: var(--cms-space-1-5);
		border: 1px solid var(--cms-color-success-600);
		border-radius: var(--cms-radius-md);
		padding: var(--cms-space-2) var(--cms-space-3);
		font-size: var(--cms-font-size-sm);
		font-weight: 600;
		color: var(--cms-color-success-600);
		background: transparent;
		box-shadow: var(--cms-shadow-sm);
		cursor: pointer;
	}

	button.cms-modal-link-file-button:focus-visible {
		outline: 2px solid var(--cms-color-success-600);
		outline-offset: 2px;
	}

	button.active {
		background-color: var(--cms-color-success-600);
		color: var(--cms-color-white);
	}
</style>
