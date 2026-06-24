<script lang="ts">
	import type { Snippet } from 'svelte';

	import { base } from '$lib/req';

	type Props = {
		class?: string;
		href: string;
		children: Snippet;
		target?: string | null;
		hxTarget?: string | null;
	};

	let { class: cls = '', href, children, target = null, hxTarget = null }: Props = $props();
</script>

<a href="{base}{href}" class={cls} {target} data-hx-target={hxTarget}>
	{@render children()}
</a>
