<script lang="ts">
	import type { Component, Snippet } from 'svelte';
	import type { HTMLButtonAttributes } from 'svelte/elements';

	let openMenu = $state(false);

	function closeMenu() {
		openMenu = false;
	}

	type Props = {
		class?: string;
		variant?: 'primary' | 'secondary' | 'danger';
		icon?: Component | null;
		label: string;
		children: Snippet<[closeMenu: () => void]>;
	};

	let {
		class: cls = 'primary',
		variant = 'primary',
		icon = null,
		label,
		children,
		...attributes
	}: Props & Omit<HTMLButtonAttributes, 'children'> = $props();
</script>

<div class="cms-button-menu">
	<button
		type="button"
		class="cms-button cms-button-{variant} cms-button-menu-main {cls}"
		{...attributes}
	>
		{#if icon}
			{@const Icon = icon}
			<span class="cms-button-icon">
				<Icon />
			</span>
		{/if}
		{label}
	</button>
	<div>
		<button
			type="button"
			class="cms-button cms-button-{variant} cms-button-menu-toggle {cls}"
			id="option-menu-button"
			aria-expanded="true"
			aria-haspopup="true"
			onclick={() => (openMenu = !openMenu)}
		>
			<span class="sr-only">Open options</span>
			<svg class="cms-button-icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
				<path
					fill-rule="evenodd"
					d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
					clip-rule="evenodd"
				/>
			</svg>
		</button>
		{#if openMenu}
			<div
				class="button-menu cms-button-menu-panel"
				role="menu"
				aria-orientation="vertical"
				aria-labelledby="option-menu-button"
				tabindex="-1"
			>
				<div class="cms-button-menu-list" role="none">
					{@render children(closeMenu)}
				</div>
			</div>
		{/if}
	</div>
</div>
