<script lang="ts">
	import { _ } from '$lib/locale';

	type Props = {
		large?: boolean;
		published?: boolean;
	};

	let { large = false, published = false }: Props = $props();
</script>

<span
	class:large
	class:published
	class="cms-published"
	title={published ? _('veröffentlicht') : _('unveröffentlicht')}
>
	{#if large}
		{published ? _('veröffentlicht') : _('unveröffentlicht')}
	{/if}
</span>

<style>
	@layer panel {
		span.cms-published {
			display: inline-block;
			border-radius: var(--radius-full);
			background-color: var(--color-neutral-300);
			font-size: var(--font-size-sm);
			height: 0.4rem;
			width: 0.4rem;

			&.large {
				padding: var(--space-px) var(--space-2);
				height: auto;
				width: auto;
				line-height: 1.25rem;
			}

			&.published {
				background-color: var(--color-success);
				color: var(--color-white);
			}
		}
	}
</style>
