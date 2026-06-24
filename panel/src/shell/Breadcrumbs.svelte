<script lang="ts">
	import IcoChevronRight from '$shell/icons/IcoChevronRight.svelte';
	import IcoDocumentTree from '$shell/icons/IcoDocumentTree.svelte';
	import Link from '$shell/Link.svelte';

	type Props = {
		href: string;
		name: string;
	};

	let { href, name }: Props = $props();
</script>

<div class="breadcrumbs">
	<IcoDocumentTree />
	<IcoChevronRight />
	<span>
		<Link {href} class="cms-breadcrumb-link">
			{name}
		</Link>
	</span>
</div>

<style>
	@layer panel {
		.breadcrumbs {
			margin-top: var(--cms-space-8);
			margin-bottom: var(--cms-space-4);
			display: flex;
			flex-direction: row;
			align-items: center;
			gap: var(--cms-space-3);
		}

		:global(.cms-breadcrumb-link:hover) {
			text-decoration: underline;
		}

		.breadcrumbs :global(svg) {
			color: var(--cms-color-neutral-400);
			display: inline-block;
		}
	}
</style>
