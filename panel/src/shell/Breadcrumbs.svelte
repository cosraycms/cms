<script lang="ts">
	import IcoChevronRight from '$shell/icons/IcoChevronRight.svelte';
	import IcoDocumentTree from '$shell/icons/IcoDocumentTree.svelte';
	import Link from '$shell/Link.svelte';

	type Props = {
		href: string;
		name: string;
		swapTarget?: string | null;
	};

	let { href, name, swapTarget = null }: Props = $props();
</script>

<nav class="cms-breadcrumbs" aria-label="Breadcrumb">
	<IcoDocumentTree />
	<IcoChevronRight />
	<span>
		<Link {href} hxTarget={swapTarget} class="cms-breadcrumb-link">
			{name}
		</Link>
	</span>
</nav>

<style>
	@layer panel {
		.cms-breadcrumbs {
			display: flex;
			min-width: 0;
			align-items: center;
			gap: var(--space-3);
			color: var(--color-text-faint);
			font-size: var(--font-size-sm);
			font-weight: 600;
		}

		.cms-breadcrumbs span {
			min-width: 0;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		:global(.cms-breadcrumb-link) {
			color: var(--color-text-muted);
			text-decoration: none;
		}

		:global(.cms-breadcrumb-link:hover) {
			color: var(--color-accent-text);
			text-decoration: underline;
		}

		.cms-breadcrumbs :global(svg) {
			color: var(--color-neutral-400);
			display: inline-block;
			flex: 0 0 auto;
		}
	}
</style>
