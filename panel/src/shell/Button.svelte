<script lang="ts">
	import type { Snippet } from 'svelte';
	import type { HTMLButtonAttributes } from 'svelte/elements';
	import type { Component } from 'svelte';

	type Props = {
		class?: string;
		variant?: 'primary' | 'secondary' | 'danger';
		icon?: Component | null;
		disabled?: boolean;
		type?: 'submit' | 'button' | 'reset';
		small?: boolean;
		children: Snippet;
	};

	let {
		class: cls = '',
		variant = 'primary',
		icon = null,
		disabled = false,
		type = 'button',
		small = false,
		children,
		...attributes
	}: Props & Omit<HTMLButtonAttributes, 'children'> = $props();
</script>

<button class="cms-button {variant} {small ? 'small' : ''} {cls}" {type} {...attributes} {disabled}>
	{#if icon}
		{@const Icon = icon}
		<span class="icon">
			<Icon />
		</span>
	{/if}
	{@render children()}
</button>
