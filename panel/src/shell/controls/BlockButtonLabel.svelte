<script lang="ts">
	type Props = {
		value: number | null;
	};

	let { value }: Props = $props();
</script>

{#if value !== null}
	<span class="block-button-label">
		{value}
	</span>
{/if}

<style>
	@layer panel {
		span.block-button-label {
			position: absolute;
			display: flex;
			align-items: center;
			justify-content: center;
			border-radius: var(--radius-full);
			background-color: var(--color-danger-700);
			font-weight: 600;
			color: var(--color-white);
			font-size: 9px;
			height: 12px;
			width: 12px;
			line-height: 9px;
			top: -13px;
			left: 3px;
		}
	}
</style>
